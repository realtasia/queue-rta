<?php defined('SYSPATH') or die('No direct script access.');
/**
 * This daemon controller handles some basic process manager stuff. It is extended
 * by other worker controllers.
 *
 * @author Jason Nathan
 * @category RealtAsia
 * @package Queue
 * @subpackage Controller_Daemon
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */
abstract class Controller_Daemon extends Controller {

    /**
     *
     * @var array the config to load for pcntl limits. Currently not used
     */
    protected $_config;

    /**
     *
     * @var boolean a property used during while loops. This get sets to true if
     * the daemon process was terminated.
     */
    protected $_sigterm;

    /**
     *
     * @var array of PIDs that are also logged to a file. Used when a kill action
     * is called.
     */
    protected $_pids = [];

    /**
     * sets config (in case) and sets pcntl handlers
     */
    public function before()
    {
        //die(phpinfo());
        parent::before();

        // Setup
        ini_set("max_execution_time", "0");
        ini_set("max_input_time", "0");
        set_time_limit(0);

        // Signal handler
        pcntl_signal(SIGCHLD, [$this, 'sig_handler']);
        pcntl_signal(SIGTERM, [$this, 'sig_handler']);
        declare(ticks = 1);

    }

    /*
     * Performs clean up. Tries (several times if neccesary)
     * to kill all children
     */
    protected function clean()
    {
        $tries = 0;

        while ($tries++ < 5 && count($this->_pids))
        {
            $this->kill_all();
            sleep(1);
        }

        if (count($this->_pids))
        {
            Kohana::$log('error', 'Queue. Could not kill all children');
        }
    }

    /*
     * Tries to kill all running children
     */
    protected function kill_all()
    {
        foreach ($this->_pids as $pid => $time)
        {
            posix_kill($pid, SIGTERM);
            usleep(500);
        }

        return count($this->_pids) === 0;
    }

    /*
     * Signal handler. Handles kill & child died signal
     */
    public function sig_handler($signo)
    {
        switch ($signo)
        {
            case SIGCHLD:
                // Child died signal
                while (($pid = pcntl_wait($status, WNOHANG || WUNTRACED)) > 0)
                {
                    // remove pid from list
                    unset($this->_pids[$pid]);
                }
                break;
            case SIGTERM:
                // Kill signal
                $this->clean();
                $this->_sigterm = TRUE;
                Kohana::$log->add('debug', 'Queue. Hit a SIGTERM');
                break;
        }
    }
}