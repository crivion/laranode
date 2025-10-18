<?php

namespace App\Actions\MySQL;

use Illuminate\Support\Facades\DB;

class GetCharsetsAndCollationsAction
{
    public function execute(): array
    {
        return [
            'charsets' => $this->getCharsets(),
            'collations' => $this->getCollations(),
        ];
    }

    private function getCharsets(): array
    {
        $charsets = DB::select("SHOW CHARACTER SET");
        
        return collect($charsets)->map(function($charset) {
            return [
                'name' => $charset->Charset,
                'description' => $charset->Description,
                'default_collation' => $charset->{'Default collation'},
                'maxlen' => $charset->Maxlen,
            ];
        })->toArray();
    }

    private function getCollations(): array
    {
        $collations = DB::select("SHOW COLLATION");
        
        return collect($collations)->map(function($collation) {
            return [
                'name' => $collation->Collation,
                'charset' => $collation->Charset,
                'id' => $collation->Id,
                'default' => $collation->Default,
                'compiled' => $collation->Compiled,
                'sortlen' => $collation->Sortlen,
            ];
        })->toArray();
    }
}
