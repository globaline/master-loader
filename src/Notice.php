<?php
/**
 * Created by PhpStorm.
 * User: kawamoto
 * Date: 16/11/11
 * Time: 10:36
 */

namespace Globaline\MasterLoader;


trait Notice
{
    protected $phase;

    /**
     * Notice constructor.
     * f
     * Set current phase 0.
     */
    public function __construct()
    {
        $this->setPhase(0);
    }

    /**
     * Set notice phase.
     *
     * @param int $phase
     */
    protected function setPhase($phase)
    {
        $this->phase = $phase;
    }

    /**
     * Get notice phase
     *
     * @return mixed
     */
    protected function getPhase()
    {
        return $this->phase;
    }

    /**
     * Echo notice.
     *
     * @param $message
     * @param string $tag
     * @param int $phase
     */
    protected function notice($message, $tag = Null, $phase = 0)
    {
        if ($phase >= $this->getPhase()) echo $tag ? "\e[32m{$tag}:\e[39m {$message}\n" : "\e[39m {$message}\n";
    }
}