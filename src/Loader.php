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
            unset($this->connection);
        }

        return $this;
    }

    /**
     * Set table from table name.
     *
     * @param null $table
     */
    public function table($table = null){
        if($table != null) {
            $this->table = $table;
            $this->columnNames = [];
            unset($this->connection);
        }

        return $this;
    }

    public function connection($connection){
        $this->connection = $connection;
    }

    /**
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

        echo "\e[32mLoading:\e[39m ".$csv." \n";

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
    }

    public function fix($csv = null)
    {
        if(empty($this->connection)){
            $this->connection = env('DB_CONNECTION', 'mysql');
        }

        $config = new LexerConfig();
        $config->setDelimiter(",");

        $interpreter = new Interpreter();
        $interpreter->addObserver(function(array $columns) use (&$lineNumber) {
            \DB::connection($this->connection)
                ->table($this->table)
                ->where($columns[2],$columns[0])
                ->update([$columns[2] => $columns[1]]);

        });

        $csv = !\File::exists($csv) or is_null($csv)
            ? database_path() .'/seeds/fixer/'.$table.'.csv'
            : $csv;

        $lexer = new Lexer($config);
        $lexer->parse($csv, $interpreter);
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
        $query = $this->getQuery($columns);

        $tables = collect(['old' => $old, 'new' => $new])->map(function($table){
            return str_contains($table, '.')
                ? ['connection' => explode('.', $table)[0], 'table' => explode('.', $table)[1]]
                : ['connection' => env('DB_CONNECTION', 'mysql'), 'table' => $table];
        });

        // Extract data from old database.
        $data = \DB::connection($tables['old']['connection'])
            ->table($tables['old']['table'])
            ->select($query)->get();

        // Apply callback function
        $data = is_callable($callback) ? $callback($data) : $data;

        // Convert stdClass object to Array
        $data = collect($data)->map(function($x){ return (array) $x; })->toArray();

        \DB::connection($tables['new']['connection'])
            ->table($tables['new']['table'])
            ->insert($data);
    }

    /**
     * Get query for DB::select method.
     * If $columns has additional column, set this value default.
     *
     * @param $columns
     * @return array
     */
    protected function getQuery($columns)
    {
        $query = [];
        $columnList = collect(\DB::connection('temp')->getSchemaBuilder()->getColumnListing('ACCOUNT'))->prepend('false');

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
}