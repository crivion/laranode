#!/usr/bin/python3

import os
import pwd
import secrets
import stat
import sys


def fail(message: str) -> None:
    print(message, file=sys.stderr)
    raise SystemExit(1)


def split_path(path: str) -> list[str]:
    if not path or path.startswith("/") or "\x00" in path:
        fail("Invalid relative path")

    parts = path.split("/")
    if any(part in ("", ".", "..") for part in parts):
        fail("Invalid relative path")

    return parts


def open_root(root: str) -> int:
    flags = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW
    try:
        return os.open(root, flags)
    except OSError as error:
        fail(f"Unable to open tenant home: {error}")


def owner_ids(owner: str):
    if owner == "-":
        return None

    try:
        account = pwd.getpwnam(owner)
    except KeyError:
        fail("Unknown system user")

    return account.pw_uid, account.pw_gid


def open_parent(root_fd: int, path: str, create: bool, owner):
    parts = split_path(path)
    current = os.dup(root_fd)

    try:
        for component in parts[:-1]:
            try:
                following = os.open(
                    component,
                    os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW,
                    dir_fd=current,
                )
            except FileNotFoundError:
                if not create:
                    raise
                os.mkdir(component, 0o770, dir_fd=current)
                if owner is not None:
                    os.chown(component, owner[0], owner[1], dir_fd=current, follow_symlinks=False)
                following = os.open(
                    component,
                    os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW,
                    dir_fd=current,
                )

            os.close(current)
            current = following

        return current, parts[-1]
    except Exception:
        os.close(current)
        raise


def write_input(handle: int, source) -> None:
    while True:
        chunk = source.read(1024 * 1024)
        if not chunk:
            break

        view = memoryview(chunk)
        while view:
            written = os.write(handle, view)
            if written <= 0:
                fail("Unable to write file contents")
            view = view[written:]


def replace(root_fd: int, path: str, owner, source, create_parents: bool = True) -> None:
    parent, name = open_parent(root_fd, path, create_parents, owner)
    temporary = f".laranode-write-{secrets.token_hex(16)}"
    handle = None

    try:
        mode = 0o660
        uid_gid = owner
        try:
            existing = os.stat(name, dir_fd=parent, follow_symlinks=False)
            if stat.S_ISREG(existing.st_mode):
                mode = stat.S_IMODE(existing.st_mode)
                uid_gid = (existing.st_uid, existing.st_gid)
            elif not stat.S_ISLNK(existing.st_mode):
                fail("Destination is not a regular file")
        except FileNotFoundError:
            pass

        handle = os.open(
            temporary,
            os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW,
            0o600,
            dir_fd=parent,
        )
        write_input(handle, source)
        os.fchmod(handle, mode)
        if uid_gid is not None:
            os.fchown(handle, uid_gid[0], uid_gid[1])
        os.fsync(handle)
        os.close(handle)
        handle = None

        os.rename(temporary, name, src_dir_fd=parent, dst_dir_fd=parent)
    finally:
        if handle is not None:
            os.close(handle)
        try:
            os.unlink(temporary, dir_fd=parent)
        except FileNotFoundError:
            pass
        os.close(parent)


def append(root_fd: int, path: str, owner, source, reset: bool) -> None:
    if reset:
        # Replacing through the pinned parent descriptor breaks an existing
        # symlink or hard link without ever truncating what it points at.
        replace(root_fd, path, owner, source, create_parents=False)
        return

    parent, name = open_parent(root_fd, path, False, owner)
    handle = None

    try:
        handle = os.open(name, os.O_WRONLY | os.O_APPEND | os.O_NOFOLLOW, dir_fd=parent)
        details = os.fstat(handle)
        if not stat.S_ISREG(details.st_mode) or details.st_nlink != 1:
            fail("Upload destination is not a private regular file")

        if owner is not None:
            os.fchmod(handle, 0o660)
            os.fchown(handle, owner[0], owner[1])

        write_input(handle, source)
        os.fsync(handle)
    finally:
        if handle is not None:
            os.close(handle)
        os.close(parent)


def copy_file(root_fd: int, source: str, destination: str, owner) -> None:
    source_parent, source_name = open_parent(root_fd, source, False, owner)
    source_handle = None

    try:
        source_handle = os.open(source_name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=source_parent)
        details = os.fstat(source_handle)
        if not stat.S_ISREG(details.st_mode):
            fail("Copy source is not a regular file")

        with os.fdopen(source_handle, "rb", closefd=False) as stream:
            replace(root_fd, destination, owner, stream)
    finally:
        if source_handle is not None:
            os.close(source_handle)
        os.close(source_parent)


def rename_path(root_fd: int, source: str, destination: str, owner) -> None:
    source_parent, source_name = open_parent(root_fd, source, False, owner)

    try:
        destination_parent, destination_name = open_parent(root_fd, destination, True, owner)
    except Exception:
        os.close(source_parent)
        raise

    try:
        # Both names are resolved relative to a descriptor reached with
        # O_NOFOLLOW, and rename() never follows a trailing symlink, so neither
        # end can be redirected out of the tenant home.
        os.rename(
            source_name,
            destination_name,
            src_dir_fd=source_parent,
            dst_dir_fd=destination_parent,
        )
    finally:
        os.close(destination_parent)
        os.close(source_parent)


def make_directory(root_fd: int, path: str, owner) -> None:
    parent, name = open_parent(root_fd, path, True, owner)

    try:
        try:
            os.mkdir(name, 0o770, dir_fd=parent)
        except FileExistsError:
            # Flysystem treats an existing directory as success. The stock
            # adapter decides that with is_dir(), which follows a symlink - this
            # checks the link itself, so a planted link is an error, not a hit.
            details = os.stat(name, dir_fd=parent, follow_symlinks=False)
            if not stat.S_ISDIR(details.st_mode):
                fail("Destination exists and is not a directory")
            return

        # mode is set through the descriptor rather than the path so umask
        # cannot narrow it and nothing can be swapped in beforehand
        handle = os.open(
            name,
            os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW,
            dir_fd=parent,
        )
        try:
            os.fchmod(handle, 0o770)
            if owner is not None:
                os.fchown(handle, owner[0], owner[1])
        finally:
            os.close(handle)
    finally:
        os.close(parent)


def empty_directory(dir_fd: int) -> None:
    for entry in os.listdir(dir_fd):
        details = os.stat(entry, dir_fd=dir_fd, follow_symlinks=False)

        if stat.S_ISDIR(details.st_mode):
            child = os.open(
                entry,
                os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW,
                dir_fd=dir_fd,
            )
            try:
                empty_directory(child)
            finally:
                os.close(child)
            os.rmdir(entry, dir_fd=dir_fd)
        else:
            # a symlink among the entries is unlinked, never descended into
            os.unlink(entry, dir_fd=dir_fd)


def remove(root_fd: int, path: str, owner, recursive: bool) -> None:
    try:
        parent, name = open_parent(root_fd, path, False, owner)
    except FileNotFoundError:
        # Flysystem's delete() is a no-op when the path is already gone
        return

    try:
        try:
            details = os.stat(name, dir_fd=parent, follow_symlinks=False)
        except FileNotFoundError:
            return

        if stat.S_ISLNK(details.st_mode):
            # remove the link itself, never what it points at
            os.unlink(name, dir_fd=parent)
        elif stat.S_ISDIR(details.st_mode):
            if not recursive:
                fail("Delete target is a directory")

            handle = os.open(
                name,
                os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW,
                dir_fd=parent,
            )
            try:
                empty_directory(handle)
            finally:
                os.close(handle)

            os.rmdir(name, dir_fd=parent)
        elif recursive:
            # deleteDirectory() ignores anything that is not a directory
            return
        else:
            os.unlink(name, dir_fd=parent)
    finally:
        os.close(parent)


def permissions(root_fd: int, path: str, owner) -> None:
    if owner is None:
        fail("An owner is required")

    parent, name = open_parent(root_fd, path, False, owner)
    handle = None

    try:
        handle = os.open(name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=parent)
        details = os.fstat(handle)
        if stat.S_ISREG(details.st_mode):
            if details.st_nlink != 1:
                fail("Permission target has more than one hard link")
            os.fchmod(handle, 0o660)
        elif stat.S_ISDIR(details.st_mode):
            os.fchmod(handle, 0o770)
        else:
            fail("Unsupported filesystem object")
        os.fchown(handle, owner[0], owner[1])
    finally:
        if handle is not None:
            os.close(handle)
        os.close(parent)


def main() -> None:
    if len(sys.argv) < 7:
        fail("Usage: safe_file.py operation root owner input-root input-name path [additional path]")

    operation, root, owner_name, input_root, input_name, *arguments = sys.argv[1:]
    owner = owner_ids(owner_name)
    root_fd = open_root(root)
    input_fd = None
    input_stream = sys.stdin.buffer

    if input_root != "-" or input_name != "-":
        if input_root == "-" or input_name == "-" or len(split_path(input_name)) != 1:
            fail("Invalid staged input")
        input_root_fd = open_root(input_root)
        try:
            input_fd = os.open(input_name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=input_root_fd)
            # validate the descriptor before consuming it: unlinking first would
            # drop st_nlink to 0 and make the link check below always fail
            input_details = os.fstat(input_fd)
            if not stat.S_ISREG(input_details.st_mode) or input_details.st_nlink != 1:
                fail("Invalid staged input")
            os.unlink(input_name, dir_fd=input_root_fd)
        finally:
            os.close(input_root_fd)
        input_stream = os.fdopen(input_fd, "rb", closefd=False)

    try:
        if operation == "replace" and len(arguments) == 1:
            replace(root_fd, arguments[0], owner, input_stream)
        elif operation == "append" and len(arguments) == 2:
            append(root_fd, arguments[0], owner, input_stream, arguments[1] == "reset")
        elif operation == "copy" and len(arguments) == 2:
            copy_file(root_fd, arguments[0], arguments[1], owner)
        elif operation == "permissions" and len(arguments) == 1:
            permissions(root_fd, arguments[0], owner)
        elif operation == "directory" and len(arguments) == 1:
            make_directory(root_fd, arguments[0], owner)
        elif operation == "rename" and len(arguments) == 2:
            rename_path(root_fd, arguments[0], arguments[1], owner)
        elif operation == "remove" and len(arguments) == 2:
            remove(root_fd, arguments[0], owner, arguments[1] == "recursive")
        else:
            fail("Invalid secure filesystem operation")
    except OSError as error:
        # a refused path (ELOOP/ENOTDIR from the O_NOFOLLOW walk) is an expected
        # outcome, not a crash - the panel shows this text, so keep it readable
        fail(f"Cannot {operation} {error.filename or ''}: {error.strerror or error}".strip())
    finally:
        if input_stream is not sys.stdin.buffer:
            input_stream.close()
        if input_fd is not None:
            os.close(input_fd)
        os.close(root_fd)


if __name__ == "__main__":
    main()
