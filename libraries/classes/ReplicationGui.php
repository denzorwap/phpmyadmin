<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Functions for the replication GUI
 *
 * @package PhpMyAdmin
 */
declare(strict_types=1);

namespace PhpMyAdmin;

use PhpMyAdmin\Core;
use PhpMyAdmin\Message;
use PhpMyAdmin\Replication;
use PhpMyAdmin\Response;
use PhpMyAdmin\Url;
use PhpMyAdmin\Util;

/**
 * Functions for the replication GUI
 *
 * @package PhpMyAdmin
 */
class ReplicationGui
{
    /**
     * @var Replication
     */
    private $replication;

    /**
     * @var Template
     */
    private $template;

    /**
     * ReplicationGui constructor.
     */
    public function __construct()
    {
        $this->replication = new Replication();
        $this->template = new Template();
    }

    /**
     * returns HTML for error message
     *
     * @return string HTML code
     */
    public function getHtmlForErrorMessage()
    {
        $html = '';
        if (isset($_SESSION['replication']['sr_action_status'])
            && isset($_SESSION['replication']['sr_action_info'])
        ) {
            if ($_SESSION['replication']['sr_action_status'] == 'error') {
                $error_message = $_SESSION['replication']['sr_action_info'];
                $html .= Message::error($error_message)->getDisplay();
                $_SESSION['replication']['sr_action_status'] = 'unknown';
            } elseif ($_SESSION['replication']['sr_action_status'] == 'success') {
                $success_message = $_SESSION['replication']['sr_action_info'];
                $html .= Message::success($success_message)->getDisplay();
                $_SESSION['replication']['sr_action_status'] = 'unknown';
            }
        }
        return $html;
    }

    /**
     * returns HTML for master replication
     *
     * @return string HTML code
     */
    public function getHtmlForMasterReplication()
    {
        if (! isset($_POST['repl_clear_scr'])) {
            $masterStatusTable = $this->getHtmlForReplicationStatusTable('master', true, false);
            $slaves = $GLOBALS['dbi']->fetchResult('SHOW SLAVE HOSTS', null, null);

            $urlParams = $GLOBALS['url_params'];
            $urlParams['mr_adduser'] = true;
            $urlParams['repl_clear_scr'] = true;
        }

        if (isset($_POST['mr_adduser'])) {
            $masterAddSlaveUser = $this->getHtmlForReplicationMasterAddSlaveUser();
        }

        return $this->template->render('server/replication/master_replication', [
            'clear_screen' => isset($_POST['repl_clear_scr']),
            'master_status_table' => $masterStatusTable ?? '',
            'slaves' => $slaves ?? [],
            'url_params' => $urlParams ?? [],
            'master_add_user' => isset($_POST['mr_adduser']),
            'master_add_slave_user' => $masterAddSlaveUser ?? '',
        ]);
    }

    /**
     * returns HTML for master replication configuration
     *
     * @return string HTML code
     */
    public function getHtmlForMasterConfiguration()
    {
        $databaseMultibox = $this->getHtmlForReplicationDbMultibox();

        return $this->template->render('server/replication/master_configuration', [
            'database_multibox' => $databaseMultibox,
        ]);
    }

    /**
     * returns HTML for slave replication configuration
     *
     * @param bool  $serverSlaveStatus      Whether it is Master or Slave
     * @param array $serverSlaveReplication Slave replication
     *
     * @return string HTML code
     */
    public function getHtmlForSlaveConfiguration(
        $serverSlaveStatus,
        array $serverSlaveReplication
    ) {
        $serverSlaveMultiReplication = $GLOBALS['dbi']->fetchResult(
            'SHOW ALL SLAVES STATUS'
        );
        if ($serverSlaveStatus) {
            $urlParams = $GLOBALS['url_params'];
            $urlParams['sr_take_action'] = true;
            $urlParams['sr_slave_server_control'] = true;

            if ($serverSlaveReplication[0]['Slave_IO_Running'] == 'No') {
                $urlParams['sr_slave_action'] = 'start';
            } else {
                $urlParams['sr_slave_action'] = 'stop';
            }

            $urlParams['sr_slave_control_parm'] = 'IO_THREAD';
            $slaveControlIoLink = Url::getCommon($urlParams, '');

            if ($serverSlaveReplication[0]['Slave_SQL_Running'] == 'No') {
                $urlParams['sr_slave_action'] = 'start';
            } else {
                $urlParams['sr_slave_action'] = 'stop';
            }

            $urlParams['sr_slave_control_parm'] = 'SQL_THREAD';
            $slaveControlSqlLink = Url::getCommon($urlParams, '');

            if ($serverSlaveReplication[0]['Slave_IO_Running'] == 'No'
                || $serverSlaveReplication[0]['Slave_SQL_Running'] == 'No'
            ) {
                $urlParams['sr_slave_action'] = 'start';
            } else {
                $urlParams['sr_slave_action'] = 'stop';
            }

            $urlParams['sr_slave_control_parm'] = null;
            $slaveControlFullLink = Url::getCommon($urlParams, '');

            $urlParams['sr_slave_action'] = 'reset';
            $slaveControlResetLink = Url::getCommon($urlParams, '');

            $urlParams = $GLOBALS['url_params'];
            $urlParams['sr_take_action'] = true;
            $urlParams['sr_slave_skip_error'] = true;
            $slaveSkipErrorLink = Url::getCommon($urlParams, '');

            $urlParams = $GLOBALS['url_params'];
            $urlParams['sl_configure'] = true;
            $urlParams['repl_clear_scr'] = true;

            $reconfigureMasterLink =  Url::getCommon($urlParams, '');

            $slaveStatusTable = $this->getHtmlForReplicationStatusTable('slave', true, false);

            $slaveIoRunning = $serverSlaveReplication[0]['Slave_IO_Running'] !== 'No';
            $slaveSqlRunning = $serverSlaveReplication[0]['Slave_SQL_Running'] !== 'No';

            $slaveErrorManagement = $this->getHtmlForSlaveErrorManagement($slaveSkipErrorLink);
        }

        return $this->template->render('server/replication/slave_configuration', [
            'server_slave_multi_replication' => $serverSlaveMultiReplication,
            'url_params' => $GLOBALS['url_params'],
            'master_connection' => $_POST['master_connection'] ?? '',
            'server_slave_status' => $serverSlaveStatus,
            'slave_status_table' => $slaveStatusTable ?? '',
            'slave_sql_running' => $slaveSqlRunning ?? false,
            'slave_io_running' => $slaveIoRunning ?? false,
            'slave_control_full_link' => $slaveControlFullLink ?? '',
            'slave_control_reset_link' => $slaveControlResetLink ?? '',
            'slave_control_sql_link' => $slaveControlSqlLink ?? '',
            'slave_control_io_link' => $slaveControlIoLink ?? '',
            'slave_error_management' => $slaveErrorManagement ?? '',
            'reconfigure_master_link' => $reconfigureMasterLink ?? '',
            'has_slave_configure' => isset($_POST['sl_configure']),
        ]);
    }

    /**
     * returns HTML for Slave Error Management
     *
     * @param string $slaveSkipErrorLink error link
     *
     * @return string HTML code
     */
    public function getHtmlForSlaveErrorManagement($slaveSkipErrorLink)
    {
        return $this->template->render('server/replication/slave_error_management', [
            'slave_skip_error_link' => $slaveSkipErrorLink,
        ]);
    }

    /**
     * returns HTML for not configure for a server replication
     *
     * @return string HTML code
     */
    public function getHtmlForNotServerReplication()
    {
        $urlParams = $GLOBALS['url_params'];
        $urlParams['mr_configure'] = true;

        return $this->template->render('server/replication/not_server_replication', [
            'url_params' => $urlParams,
        ]);
    }

    /**
     * returns HTML code for selecting databases
     *
     * @return string HTML code
     */
    public function getHtmlForReplicationDbMultibox()
    {
        $databases = [];
        foreach ($GLOBALS['dblist']->databases as $database) {
            if (! $GLOBALS['dbi']->isSystemSchema($database)) {
                $databases[] = $database;
            }
        }

        return $this->template->render('server/replication/database_multibox', [
            'databases' => $databases,
        ]);
    }

    /**
     * returns HTML for changing master
     *
     * @param string $submitName submit button name
     *
     * @return string HTML code
     */
    public function getHtmlForReplicationChangeMaster($submitName)
    {
        list(
            $usernameLength,
            $hostnameLength
        ) = $this->getUsernameHostnameLength();

        return $this->template->render('server/replication/change_master', [
            'server_id' => time(),
            'username_length' => $usernameLength,
            'hostname_length' => $hostnameLength,
            'submit_name' => $submitName,
        ]);
    }

    /**
     * This function returns html code for table with replication status.
     *
     * @param string  $type   either master or slave
     * @param boolean $hidden if true, then default style is set to hidden,
     *                        default value false
     * @param boolean $title  if true, then title is displayed, default true
     *
     * @return string HTML code
     */
    public function getHtmlForReplicationStatusTable($type, $hidden = false, $title = true)
    {
        global ${"{$type}_variables"};
        global ${"{$type}_variables_alerts"};
        global ${"{$type}_variables_oks"};
        global ${"server_{$type}_replication"};
        global ${"strReplicationStatus_{$type}"};

        $html = '';

        // TODO check the Masters server id?
        // seems to default to '1' when queried via SHOW VARIABLES ,
        // but resulted in error on the master when slave connects
        // [ERROR] Error reading packet from server: Misconfigured master
        // - server id was not set ( server_errno=1236)
        // [ERROR] Got fatal error 1236: 'Misconfigured master
        // - server id was not set' from master when reading data from binary log
        //
        //$server_id = $GLOBALS['dbi']->fetchValue(
        //    "SHOW VARIABLES LIKE 'server_id'", 0, 1
        //);

        $html .= '<div id="replication_' . $type . '_section" style="';
        $html .= ($hidden ? 'display: none;' : '') . '"> ';

        if ($title) {
            if ($type == 'master') {
                $html .= '<h4><a name="replication_' . $type . '"></a>';
                $html .= __('Master status') . '</h4>';
            } else {
                $html .= '<h4><a name="replication_' . $type . '"></a>';
                $html .= __('Slave status') . '</h4>';
            }
        } else {
            $html .= '<br>';
        }

        $html .= '   <table id="server' . $type . 'replicationsummary" class="data"> ';
        $html .= '   <thead>';
        $html .= '    <tr>';
        $html .= '     <th>' . __('Variable') . '</th>';
        $html .= '        <th>' . __('Value') . '</th>';
        $html .= '    </tr>';
        $html .= '   </thead>';
        $html .= '   <tbody>';

        foreach (${"{$type}_variables"} as $variable) {
            $html .= '   <tr>';
            $html .= '     <td class="name">';
            $html .= htmlspecialchars($variable);
            $html .= '     </td>';
            $html .= '     <td class="value">';

            // TODO change to regexp or something, to allow for negative match
            if (isset(${"{$type}_variables_alerts"}[$variable])
                && ${"{$type}_variables_alerts"}[$variable] == ${"server_{$type}_replication"}[0][$variable]
            ) {
                $html .= '<span class="attention">';
            } elseif (isset(${"{$type}_variables_oks"}[$variable])
                && ${"{$type}_variables_oks"}[$variable] == ${"server_{$type}_replication"}[0][$variable]
            ) {
                $html .= '<span class="allfine">';
            } else {
                $html .= '<span>';
            }
            // allow wrapping long table lists into multiple lines
            $variables_wrap = [
                'Replicate_Do_DB',
                'Replicate_Ignore_DB',
                'Replicate_Do_Table',
                'Replicate_Ignore_Table',
                'Replicate_Wild_Do_Table',
                'Replicate_Wild_Ignore_Table',
            ];
            if (in_array($variable, $variables_wrap)) {
                $html .= htmlspecialchars(str_replace(
                    ',',
                    ', ',
                    ${"server_{$type}_replication"}[0][$variable]
                ));
            } else {
                $html .= htmlspecialchars(${"server_{$type}_replication"}[0][$variable]);
            }
            $html .= '</span>';

            $html .= '  </td>';
            $html .= ' </tr>';
        }

        $html .= '   </tbody>';
        $html .= ' </table>';
        $html .= ' <br>';
        $html .= '</div>';

        return $html;
    }

    /**
     * get the correct username and hostname lengths for this MySQL server
     *
     * @return array   username length, hostname length
     */
    public function getUsernameHostnameLength()
    {
        $fields_info = $GLOBALS['dbi']->getColumns('mysql', 'user');
        $username_length = 16;
        $hostname_length = 41;
        foreach ($fields_info as $val) {
            if ($val['Field'] == 'User') {
                strtok($val['Type'], '()');
                $v = strtok('()');
                if (is_int($v)) {
                    $username_length = $v;
                }
            } elseif ($val['Field'] == 'Host') {
                strtok($val['Type'], '()');
                $v = strtok('()');
                if (is_int($v)) {
                    $hostname_length = $v;
                }
            }
        }
        return [
            $username_length,
            $hostname_length,
        ];
    }

    /**
     * returns html code to add a replication slave user to the master
     *
     * @return string HTML code
     */
    public function getHtmlForReplicationMasterAddSlaveUser()
    {
        list(
            $usernameLength,
            $hostnameLength
        ) = $this->getUsernameHostnameLength();

        if (isset($_POST['username']) && strlen($_POST['username']) === 0) {
            $GLOBALS['pred_username'] = 'any';
        }

        $username = '';
        if (! empty($_POST['username'])) {
            $username = $GLOBALS['new_username'] ?? $_POST['username'];
        }

        $currentUser = $GLOBALS['dbi']->fetchValue('SELECT USER();');
        if (! empty($currentUser)) {
            $userHost = str_replace(
                "'",
                '',
                mb_substr(
                    $currentUser,
                    mb_strrpos($currentUser, '@') + 1
                )
            );
            if ($userHost !== 'localhost' && $userHost !== '127.0.0.1') {
                $thisHost = $userHost;
            }
        }

        // when we start editing a user, $GLOBALS['pred_hostname'] is not defined
        if (! isset($GLOBALS['pred_hostname']) && isset($_POST['hostname'])) {
            switch (mb_strtolower($_POST['hostname'])) {
                case 'localhost':
                case '127.0.0.1':
                    $GLOBALS['pred_hostname'] = 'localhost';
                    break;
                case '%':
                    $GLOBALS['pred_hostname'] = 'any';
                    break;
                default:
                    $GLOBALS['pred_hostname'] = 'userdefined';
                    break;
            }
        }

        return $this->template->render('server/replication/master_add_slave_user', [
            'username_length' => $usernameLength,
            'hostname_length' => $hostnameLength,
            'has_username' => isset($_POST['username']),
            'username' => $username,
            'hostname' => $_POST['hostname'] ?? '',
            'predefined_username' => $GLOBALS['pred_username'] ?? '',
            'predefined_hostname' => $GLOBALS['pred_hostname'] ?? '',
            'this_host' => $thisHost ?? null,
        ]);
    }

    /**
     * handle control requests
     *
     * @return void
     */
    public function handleControlRequest()
    {
        if (isset($_POST['sr_take_action'])) {
            $refresh = false;
            $result = false;
            $messageSuccess = null;
            $messageError = null;

            if (isset($_POST['slave_changemaster']) && ! $GLOBALS['cfg']['AllowArbitraryServer']) {
                $_SESSION['replication']['sr_action_status'] = 'error';
                $_SESSION['replication']['sr_action_info'] = __('Connection to server is disabled, please enable $cfg[\'AllowArbitraryServer\'] in phpMyAdmin configuration.');
            } elseif (isset($_POST['slave_changemaster'])) {
                $result = $this->handleRequestForSlaveChangeMaster();
            } elseif (isset($_POST['sr_slave_server_control'])) {
                $result = $this->handleRequestForSlaveServerControl();
                $refresh = true;

                switch ($_POST['sr_slave_action']) {
                    case 'start':
                        $messageSuccess = __('Replication started successfully.');
                        $messageError = __('Error starting replication.');
                        break;
                    case 'stop':
                        $messageSuccess = __('Replication stopped successfully.');
                        $messageError = __('Error stopping replication.');
                        break;
                    case 'reset':
                        $messageSuccess = __('Replication resetting successfully.');
                        $messageError = __('Error resetting replication.');
                        break;
                    default:
                        $messageSuccess = __('Success.');
                        $messageError = __('Error.');
                        break;
                }
            } elseif (isset($_POST['sr_slave_skip_error'])) {
                $result = $this->handleRequestForSlaveSkipError();
            }

            if ($refresh) {
                $response = Response::getInstance();
                if ($response->isAjax()) {
                    $response->setRequestStatus($result);
                    $response->addJSON(
                        'message',
                        $result
                        ? Message::success($messageSuccess)
                        : Message::error($messageError)
                    );
                } else {
                    Core::sendHeaderLocation(
                        './server_replication.php'
                        . Url::getCommonRaw($GLOBALS['url_params'])
                    );
                }
            }
            unset($refresh);
        }
    }

    /**
     * handle control requests for Slave Change Master
     *
     * @return boolean
     */
    public function handleRequestForSlaveChangeMaster()
    {
        $sr = [];
        $_SESSION['replication']['m_username'] = $sr['username']
            = $GLOBALS['dbi']->escapeString($_POST['username']);
        $_SESSION['replication']['m_password'] = $sr['pma_pw']
            = $GLOBALS['dbi']->escapeString($_POST['pma_pw']);
        $_SESSION['replication']['m_hostname'] = $sr['hostname']
            = $GLOBALS['dbi']->escapeString($_POST['hostname']);
        $_SESSION['replication']['m_port']     = $sr['port']
            = $GLOBALS['dbi']->escapeString($_POST['text_port']);
        $_SESSION['replication']['m_correct']  = '';
        $_SESSION['replication']['sr_action_status'] = 'error';
        $_SESSION['replication']['sr_action_info'] = __('Unknown error');

        // Attempt to connect to the new master server
        $link_to_master = $this->replication->connectToMaster(
            $sr['username'],
            $sr['pma_pw'],
            $sr['hostname'],
            $sr['port']
        );

        if (! $link_to_master) {
            $_SESSION['replication']['sr_action_status'] = 'error';
            $_SESSION['replication']['sr_action_info'] = sprintf(
                __('Unable to connect to master %s.'),
                htmlspecialchars($sr['hostname'])
            );
        } else {
            // Read the current master position
            $position = $this->replication->slaveBinLogMaster($link_to_master);

            if (empty($position)) {
                $_SESSION['replication']['sr_action_status'] = 'error';
                $_SESSION['replication']['sr_action_info']
                    = __(
                        'Unable to read master log position. '
                        . 'Possible privilege problem on master.'
                    );
            } else {
                $_SESSION['replication']['m_correct']  = true;

                if (! $this->replication->slaveChangeMaster(
                    $sr['username'],
                    $sr['pma_pw'],
                    $sr['hostname'],
                    $sr['port'],
                    $position,
                    true,
                    false
                )
                ) {
                    $_SESSION['replication']['sr_action_status'] = 'error';
                    $_SESSION['replication']['sr_action_info']
                        = __('Unable to change master!');
                } else {
                    $_SESSION['replication']['sr_action_status'] = 'success';
                    $_SESSION['replication']['sr_action_info'] = sprintf(
                        __('Master server changed successfully to %s.'),
                        htmlspecialchars($sr['hostname'])
                    );
                }
            }
        }

        return $_SESSION['replication']['sr_action_status'] === 'success';
    }

    /**
     * handle control requests for Slave Server Control
     *
     * @return boolean
     */
    public function handleRequestForSlaveServerControl()
    {
        if (empty($_POST['sr_slave_control_parm'])) {
            $_POST['sr_slave_control_parm'] = null;
        }
        if ($_POST['sr_slave_action'] == 'reset') {
            $qStop = $this->replication->slaveControl("STOP");
            $qReset = $GLOBALS['dbi']->tryQuery("RESET SLAVE;");
            $qStart = $this->replication->slaveControl("START");

            $result = ($qStop !== false && $qStop !== -1 &&
                $qReset !== false && $qReset !== -1 &&
                $qStart !== false && $qStart !== -1);
        } else {
            $qControl = $this->replication->slaveControl(
                $_POST['sr_slave_action'],
                $_POST['sr_slave_control_parm']
            );

            $result = ($qControl !== false && $qControl !== -1);
        }

        return $result;
    }

    /**
     * handle control requests for Slave Skip Error
     *
     * @return boolean
     */
    public function handleRequestForSlaveSkipError()
    {
        $count = 1;
        if (isset($_POST['sr_skip_errors_count'])) {
            $count = $_POST['sr_skip_errors_count'] * 1;
        }

        $qStop = $this->replication->slaveControl("STOP");
        $qSkip = $GLOBALS['dbi']->tryQuery(
            "SET GLOBAL SQL_SLAVE_SKIP_COUNTER = " . $count . ";"
        );
        $qStart = $this->replication->slaveControl("START");

        $result = ($qStop !== false && $qStop !== -1 &&
            $qSkip !== false && $qSkip !== -1 &&
            $qStart !== false && $qStart !== -1);

        return $result;
    }
}
