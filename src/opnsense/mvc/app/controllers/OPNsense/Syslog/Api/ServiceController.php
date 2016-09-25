<?php

/**
 *    Copyright (C) 2016 E.Bevz & Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Syslog\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Syslog\Syslog;
use \OPNsense\Core\Config;

/**
 * Class ServiceController
 * @package OPNsense\Syslog
 */
class ServiceController extends ApiControllerBase
{

    /**
     * restart syslog service
     * @return array
     */
    public function reloadAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $backend = new Backend();

            // generate template
            $backend->configdRun("template reload OPNsense.Syslog");

            // (res)start daemon
            $backend->configdRun("syslog stop");
            $message = $backend->configdRun("syslog start");

            return array("status" => "ok", "message" => $message);
        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }

    /**
     * delete log files
     * @return array
     */
    public function resetLogFilesAction()
    {
        if ($this->request->isPost()) {

            $this->sessionClose();

            $mdl = new Syslog();
            $result = $mdl->resetLogFiles();

            $message = gettext("Log Files Deleted");

            return array("status" => "ok", "message" => $message);
        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }

    /**
     * clear custom log
     * @return array
     */
    public function clearLogAction()
    {
        if ($this->request->isPost()) {

            $this->sessionClose();

            $mdl = new Syslog();
            $message = $mdl->clearLog($this->request->getPost('logname'));

            return array("status" => "ok", "message" => $message);
        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }

    /**
     * clear custom log
     * @return array
     */
    public function getlogAction()
    {
        if ($this->request->isPost()) {

            $logname = $this->request->getPost('logname');
            $filter = $this->request->getPost('filter');

            $this->sessionClose();

            $mdl = new Syslog();
            $filename = $mdl->getLogFileName($logname);
            $reverse = ($mdl->Reverse->__toString() == '1');
            $numentries = intval($mdl->NumEntries->__toString());
            $hostname = Config::getInstance()->toArray()['system']['hostname'];

            $logdata = array();
            $formatted = array();
            if($filename != '') {
                $backend = new Backend();
                $logdatastr = $backend->configdRun("syslog dumplog {$filename}");
                $logdata = explode("\n", $logdatastr);
            }

            $filters = preg_split('/\s+/', trim($filter));
            foreach ($filters as $pattern) {
                if(trim($pattern) == '')
                    continue;
                $logdata = preg_grep("/$pattern/", $logdata);
            }

            if($reverse)
                $logdata = array_reverse($logdata);

            $counter = 1;
            foreach ($logdata as $logent) {
                if(trim($logent) == '')
                    continue;

                $logent = preg_split("/\s+/", $logent, 6);
                $entry_date_time = join(" ", array_slice($logent, 0, 3));
                $entry_text = ($logent[3] == $hostname) ? "" : $logent[3] . " ";
                $entry_text .= (isset($logent[4]) ?  $logent[4] : '') . (isset($logent[5]) ? " " . $logent[5] : '');
                $formatted[] = array('time' => $entry_date_time, 'filter' => $filter, 'message' => $entry_text);

                if(++$counter > $numentries)
                    break; 
            }

            return array("status" => "ok", "data" => $formatted, 'filters' => $filters);

        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }
}
