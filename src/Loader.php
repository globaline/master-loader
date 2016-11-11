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
    use SqlLoader, Relocater, Notice;

    /**
     * The table name connected.
     *
     * @var string
     */
    protected $table;

    /**
     * The column list of current table.
     *
     * @var array
     */
    protected $columnNames = [];

    /**
     * The laravel connection settings name connected.
     *
     * @var string
     */
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
        $this->notice($table, 'Loaded');
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

        $this->notice($table, 'Fixed');
    }
}