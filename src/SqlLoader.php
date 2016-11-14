<?php

namespace Globaline\MasterLoader;


trait SqlLoader
{
    protected $mysql_access;

    /**
     * Generate MySQL access file.
     *
     * @param bool $regenerate
     */
    protected function generateMySQLAccess($regenerate = false)
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
        $this->notice($database, 'Imported');
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
        $this->notice($database, 'Exported');
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
        $this->setPhase(1);
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

        $this->modifyDatabaseCollation('temp', 'utf8_general_ci');
        foreach ($this->getTableList('temp') As $table){
            $this->modifyTableCollation('temp.'.$table, 'utf8_general_ci');
        }

        $this->setPhase(0);

        if(is_callable($callback)) $callback(\DB::connection('temp'));

        \Config::offsetUnset('database.connections.temp');
        \DB::statement("drop database if exists temp;");
    }

    public function getTableList($connection = Null)
    {
        $connection = ($this->connection && !$connection) ? $this->connection
            : ($connection) ? $connection
            : \DB::getDefaultConnection();

        $tables = \DB::connection($connection)->select('SHOW TABLES');

        return collect($tables)->map(function($table) {
            $table = (array) $table;
            return $table['Tables_in_temp'];
        });
    }

    public function modifyDatabaseCollation($database, $collation){


        \DB::statement("SET SESSION sql_mode='ALLOW_INVALID_DATES'");
        \DB::getPdo()->prepare("ALTER DATABASE `$database` COLLATE '$collation';");
    }

    public function modifyTableCollation($table, $collation)
    {
        \DB::statement("SET SESSION sql_mode='ALLOW_INVALID_DATES'");
        \DB::statement("ALTER TABLE $table COLLATE '$collation';");
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
}