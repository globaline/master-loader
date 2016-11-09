<?php

namespace Globaline\MasterLoader;

use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MasterLoader
 */
class Loader
{
    /**
     * @var string
     */
    protected $table;

    /**
     * @var Model
     */
    protected $model;

    /**
     * @var array
     */
    protected $columnNames = [];

    protected $connection;

    /**
     * Set table from Model class
     *
     * @param Model $model
     */
    public function model(Model $model = null)
    {
        if($model != null) {
            $this->table = $model->getTable();
            $this->columnNames = [];
            $this->connection($model->getConnectionName());
        }

        return $this;
    }

    /**
     * Set table from table name.
     *
     * @param null $table
     */
    public function table($table = null)
    {
        if($table != null) {
            if (str_contains($table, '.')) {
                $connect = explode('.', $table);
                if (count($connect) < 3) {
                    $this->connection($connect[0]);
                    $this->table = $connect[1];
                }
            } else {
                $this->connection(isset($this->connection) ? $this->connection : env('DB_CONNECTION', 'mysql'));
                $this->table = $table;
            }
            $this->columnNames = [];
        }

        return $this;
    }

    /**
     * Set DB connection.
     *
     * @param $connection
     * @return $this
     */
    public function connection($connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Loading records from csv file.
     *
     * @param null $csv
     * @param bool $addition
     */
    public function load($csv = null, $addition = false)
    {
        $table = $this->table;

        if (!\File::exists($csv) and !is_null($csv)) {
            throw new FileNotFoundException("File does not exist at path {$csv}");
        }

        $csv = is_null($csv)
            ? database_path() . '/seeds/master/'.$table.'.csv'
            : $csv;

        $insertData = [];

        if(!(bool)$addition){
            \DB::table($table)->truncate();
        }

        $config = new LexerConfig();
        $config->setDelimiter(",");

        $interpreter = new Interpreter();
        $interpreter->addObserver(function(array $record) use (&$insertData){
            if ($this->columnNames == []){
                foreach ($record As $column){
                    array_push($this->columnNames, $column);
                }

            } else {
                $insertLine = [];
                for($count = 0; $count < count($this->columnNames); $count++){
                    $insertLine[$this->columnNames[$count]] = $record[$count];
                }

                $timestamp = [
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s")
                ];

                $insertData[] = array_merge($insertLine, $timestamp);
            }
        });

        $lexer = new Lexer($config);
        $lexer->parse($csv, $interpreter);

        \DB::table($table)->insert($insertData);
        echo "\e[32mLoaded:\e[39m ".$table." \n";
    }

    /**
     * Fixing tables with csv file.
     *
     * @param null $csv
     */
    public function fix($csv = null)
    {
        $table = $this->table;
        $connection = $this->connection;

        if (!\File::exists($csv) and !is_null($csv)) {
            throw new \Exception("File does not exist at path {$csv}");
        }

        $csv = is_null($csv) ? database_path() .'/seeds/fixer/'.$table.'.csv' : $csv;
        $config = new LexerConfig();
        $config->setDelimiter(",");

        $interpreter = new Interpreter();
        $interpreter->addObserver(function(array $columns) use ($connection, $table)
        {
            \DB::connection($connection)
                ->table($table)
                ->where($columns[2], $columns[0])
                ->update([$columns[2] => $columns[1]]);
        });

        $lexer = new Lexer($config);
        $lexer->parse($csv, $interpreter);

        echo "\e[32mFixed:\e[39m ".$table." \n";
    }

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
        $data = collect($data)->map(function($x){ return (array) $x; })->toArray();

        \DB::connection($tables['new']['connection'])
            ->table($tables['new']['table'])
            ->insert($data);
        echo "\e[32mRelocated:\e[39m ".$tables['old']['table']." to ".$tables['new']['table']." \n";
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

    protected $mysql_access;

    /**
     * Generate MySQL access file.
     *
     * @param bool $regenerate
     */
    protected function generateMySQLAccess(Bool $regenerate = false)
    {
        $access_file = storage_path()."/framework/cache/db_access.cnf";

        if (!\File::exists($access_file) or $regenerate) {
            if (\File::exists($access_file) and $regenerate) \File::delete($access_file);
            $access = "[client]\nuser=".env('DB_USERNAME','')."\npassword=".env('DB_PASSWORD','')."\nhost=".env('DB_HOST','');
            \File::append($access_file, $access);
        }
        chmod($access_file, 400);

        $this->mysql_access = $access_file;
    }

    /**
     * Import records from sql file.
     *
     * @param $path
     * @param null $database
     */
    public function importSQL($path, $database = null)
    {
        $database = isset($database) ? $database : env('DB_DATABASE', 'forge');

        if (!isset($this->mysql_access)) $this->generateMySQLAccess();
        exec('mysql --defaults-extra-file='.$this->mysql_access.' '.$database.' < '.$path);
        echo "\e[32mImported:\e[39m ".$database." \n";
    }

    /**
     * Export records from sql file.
     *
     * @param $path
     * @param null $database
     */
    public function exportSQL($path, $database = null)
    {
        $database = isset($database) ? $database : env('DB_DATABASE', 'forge');

        if (!isset($this->mysql_access)) $this->generateMySQLAccess();
        exec('mysqldump --defaults-extra-file='.$this->mysql_access.' '.$database.' > '.$path);
        echo "\e[32mExported:\e[39m ".$database." \n";
    }

    /**
     * Create temoporary database from existing database.
     * After running callback function, the database will remove.
     * The temporary database can be refered from Lodaer::temp($tabake_name) method
     * when callback function is running.
     *
     * @param $callback
     * @param null $database
     */
    public function temporaryDuplicate($callback, $database = null)
    {
        $temp_file = storage_path()."/temp.sql";
        $database =  isset($database) ? $database : env('DB_DATABASE', 'forge');

        $this->exportSQL($temp_file, $database);
        \DB::statement("create database if not exists temp;");
        $this->importSQL($temp_file, 'temp');
        \File::delete($temp_file);

        \Config::set('database.connections.temp', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '3306'),
            'database' => 'temp',
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ]);

        if(is_callable($callback)) $callback(\DB::connection('temp'));

        \Config::offsetUnset('database.connections.temp');
        \DB::statement("drop database if exists temp;");
    }

    /**
     * Return connection of temporary database.
     *
     * @param null $table
     * @return \Illuminate\Database\Connection|\Illuminate\Database\Query\Builder
     */
    public function temp($table = null)
    {
        if (!\Config::has('database.connections.temp')){
            throw new \Exception("Temporary database is not exist.\n".
                "Loader::temp() can be used in Closure of temporaryDumplicate method.");
        }

        if (isset($table)) {
            return \DB::connection('temp')->table($table);
        } else {
            return \DB::connection('temp');
        }
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
        $parent = is_array($parent) ? $parent : [$parent, 'name'];
        $child = is_array($child) ? $child : [$child, 'name'];

        if(!\Schema::hasTable($parent[0])){
            throw new \Exception("Table \"{$parent[0]}\" is not exist.");
        } else if(!\Schema::hasTable($child[0])){
            throw new \Exception("Table \"{$child[0]}\" is not exist.");
        }

        $foreign_key = $foreign_key ?: $this->getForeignKey($parent[0]);

        $sql = "UPDATE {$child[0]}, {$parent[0]} SET {$child[0]}.{$foreign_key} = {$parent[0]}.id
                WHERE ({$child[0]}.{$child[1]} != \"\" AND {$parent[0]}.{$parent[1]} like CONCAT(\"%\", {$child[0]}.{$child[1]}, \"%\")) 
                OR ({$parent[0]}.{$parent[1]} != \"\" AND {$child[0]}.{$child[1]} like CONCAT(\"%\", {$parent[0]}.{$parent[1]}, \"%\"))";

        \DB::statement($sql);

        echo "\e[32mSuccess:\e[39m Set up relation ".$parent[0]." to ".$child[0]."\n";
    }

    /**
     * Get the default foreign key name.
     *
     * @return string
     */
    public function getForeignKey($table)
    {
        return snake_case(str_singular($table)).'_id';
    }

}