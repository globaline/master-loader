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
    protected $columnNames = array();

    protected $connection;

    /**
     * MasterLoader constructor.
     * @param Model $model
     */
    public function __construct(Model $model = null)
    {
        if($model != null) {
            $this->table = $model->getTable();
            $this->model = $model;
        }
    }

    public function setConnection($connection){
        $this->connection = $connection;
    }

    /**
     * @param bool $addition
     */
    public function load($addition = false)
    {
        $table = $this->table;
        $addition = (bool)$addition;

        if(!$addition){
            DB::table($table)->truncate();
        }

        $config = new LexerConfig();
        $config->setDelimiter(",");

        $interpreter = new Interpreter();
        $lineNumber = 0;
        $interpreter->addObserver(function(array $columns) use (&$lineNumber) {
            $model = $this->model->newInstance();

            $lineNumber += 1;
            if ($lineNumber == 1){
                foreach ($columns As $column){
                    array_push($this->columnNames, $column);
                }

            } else {
                for($count = 0; $count < count($this->columnNames); $count++)
                {
                    $columnName = $this->columnNames[$count];
                    $model->$columnName = $columns[$count];
                }
                $model->save();
            }
        });

        $lexer = new Lexer($config);
        $lexer->parse(base_path().'/database/seeds/master/'.$table.'.csv', $interpreter);
    }

    public function fix($table)
    {
        if(!empty($table)){
            $this->table = $table;
        }

        if(empty($this->connection)){
            $this->connection = env('DB_CONNECTION', 'mysql');
        }

        $config = new LexerConfig();
        $config->setDelimiter(",");

        $interpreter = new Interpreter();
        $interpreter->addObserver(function(array $columns) use (&$lineNumber) {
            DB::connection($this->connection)
                ->table($this->table)
                ->where($columns[2],$columns[0])
                ->update([$columns[2] => $columns[1]]);

        });

        $lexer = new Lexer($config);
        $lexer->parse(base_path().'/database/seeds/fixer/'.$table.'.csv', $interpreter);
    }
}