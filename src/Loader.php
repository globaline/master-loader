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
        if($model != null) {
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
        $csv = !\File::exists($csv) or is_null($csv)
            ? database_path() . '/seeds/master/'.$table.'.csv'
            : $csv;

        echo "\e[32mLoading:\e[39m ".$csv." \n";

        $table = $this->table;
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
}