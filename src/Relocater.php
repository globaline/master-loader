<?php

namespace Globaline\MasterLoader;


trait Relocater
{
    /**
     * Relocate data from old table to new table.
     *
     * @param $old
     * @param $new
     * @param $columns
     * @param null $callback
     */
    public function relocate($old, $new, $columns, $callback = null)
    {
        $tables = collect(['old' => $old, 'new' => $new])->map(function($table){
            return str_contains($table, '.')
                ? ['connection' => explode('.', $table)[0], 'table' => explode('.', $table)[1]]
                : ['connection' => env('DB_CONNECTION', 'mysql'), 'table' => $table];
        });

        $query = $this->getQuery($columns, $tables['old']);

        // Extract data from old database.
        $data = \DB::connection($tables['old']['connection'])
            ->table($tables['old']['table'])
            ->select($query);

        // Apply callback function
        $data = is_callable($callback) ? $callback($data)->get() : $data->get();

        // Convert stdClass object to Array
        $data = collect($data)->map(function($x){ return (array) $x; })->chunk(1000);

        foreach($data as $insert_data) {
            \DB::connection($tables['new']['connection'])
                ->table($tables['new']['table'])
                ->insert($insert_data->toArray());
        }
        $this->notice("{$tables['old']['table']} to {$tables['new']['table']}", 'Relocated', 1);
    }

    /**
     * Get query for DB::select method.
     * If $columns has additional column, set this value default.
     *
     * @param $columns
     * @return array
     */
    protected function getQuery($columns, $table)
    {
        $query = [];
        $columnList = collect(\DB::connection($table['connection'])
            ->getSchemaBuilder()->getColumnListing($table['table']))->prepend('false');

        foreach($columns As $key => $value) {
            $addition = is_string($key) ? !$columnList->search($key) : true;
            if ($addition !== false){
                $query[] = \DB::raw("\"".$key."\"".' As '.$value);
            } else {
                $query[] = $key.' As '.$value;
            }
        }
        return $query;
    }

    /**
     * Search words and set relation method.
     *
     * @param $parent
     * @param $child
     * @param null $foreign_key
     * @throws \Exception
     */
    public function setRelation($parent, $child, $foreign_key = null)
    {
        $this->parseTable($parent);
        $this->parseTable($child);

        $foreign_key = $foreign_key ?: $this->getForeignKey($parent['table']);

        $sql = "UPDATE {$child['db']}.{$child['table']}, {$parent['db']}.{$parent['table']}
                SET {$child['table']}.{$foreign_key} = {$parent['table']}.id
                WHERE ({$child['table']}.{$child['column']} != \"\" 
                  AND {$parent['table']}.{$parent['column']} like CONCAT(\"%\", {$child['table']}.{$child['column']}, \"%\")) 
                OR ({$parent['table']}.{$parent['column']} != \"\" 
                  AND {$child['table']}.{$child['column']} like CONCAT(\"%\", {$parent['table']}.{$parent['column']}, \"%\"))";


        \DB::statement($sql);

        $this->notice("Replace {$child['table']}.{$child['column']} to {$parent['table']}.{$foreign_key}", 'Success');
    }

    /**
     * Get the default foreign key name.
     *
     * @return string
     */
    protected function getForeignKey($table)
    {
        return snake_case(str_singular($table)).'_id';
    }

    /**
     * Parse table info params to array
     *
     * @param $table
     * @return array
     */
    protected function parseTable(&$table)
    {
        $data = is_string($table) && str_contains($table, '.') ? explode('.', $table) : $table;
        $data = is_array($data) ? $data : [$data];

        if (count($data) <= 2) $data = array_prepend($data, \DB::getDatabaseName());
        if (count($data) == 2) array_push($data, 'name');

        return $table = ['db' => $data[0], 'table' => $data[1], 'column' => $data[2]];
    }

}