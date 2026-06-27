<?php

// The persisted theme must be applied before first paint (inline <head> script),
// otherwise a hard reload in dark mode flashes light before React re-applies it.
test('the root document inlines the pre-paint dark-theme bootstrap', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee("localStorage.getItem('theme')", escape: false);
    $response->assertSee("classList.add('dark')", escape: false);
});
