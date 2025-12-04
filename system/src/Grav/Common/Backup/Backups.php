<?php

/**
 * @package    Grav\Common\Backup
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Backup;

use DateTime;
use Exception;
use FilesystemIterator;
use GlobIterator;
use Grav\Common\Filesystem\Archiver;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Inflector;
use Grav\Common\Scheduler\Job;
use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Utils;
use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\JsonFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use SplFileInfo;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function count;

/**
 * Class Backups
 * @package Grav\Common\Backup
 */
class Backups
{
    protected const BACKUP_FILENAME_REGEXZ = "#(.*)--(\d*).zip#";

    protected const BACKUP_DATE_FORMAT = 'YmdHis';

    /** @var string */
    protected static $backup_dir;

    /** @var array|null */
    protected static $backups;

    /**
     * @return void
     */
    public function init()
    {
        $grav = Grav::instance();

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $grav['events'];
        $dispatcher->addListener('onSchedulerInitialized', $this->onSchedulerInitialized(...));

        $grav->fireEvent('onBackupsInitialized', new Event(['backups' => $this]));
    }

    /**
     * @return void
     */
    public function setup()
    {
        if (null === static::$backup_dir) {
            $grav = Grav::instance();
            static::$backup_dir = $grav['locator']->findResource('backup://', true, true);
            Folder::create(static::$backup_dir);
        }
    }

    /**
     * @param Event $event
     * @return void
     */
    public function onSchedulerInitialized(Event $event)
    {
        $grav = Grav::instance();

        /** @var Scheduler $scheduler */
        $scheduler = $event['scheduler'];

        /** @var Inflector $inflector */
        $inflector = $grav['inflector'];

        foreach (static::getBackupProfiles() as $id => $profile) {
            $at = $profile['schedule_at'];
            $name = $inflector::hyphenize($profile['name']);
            $logs = 'logs/backup-' . $name . '.out';
            $environment = $profile['schedule_environment'] ?? null;
            /** @var Job $job */
            $job = $scheduler->addFunction('Grav\Common\Backup\Backups::backup', [$id, null, $environment], $name);
            $job->at($at);
            $job->output($logs);
            $job->backlink('/tools/backups');
        }
    }

    /**
     * @param string $backup
     * @param string $base_url
     * @return string
     */
    public function getBackupDownloadUrl($backup, $base_url)
    {
        $param_sep = Grav::instance()['config']->get('system.param_sep', ':');
        $download = urlencode(base64_encode(Utils::basename($backup)));
        $url      = rtrim((string) Grav::instance()['uri']->rootUrl(true), '/') . '/' . trim(
            $base_url,
            '/'
        ) . '/task' . $param_sep . 'backup/download' . $param_sep . $download . '/admin-nonce' . $param_sep . Utils::getNonce('admin-form');

        return $url;
    }

    /**
     * @return array
     */
    public static function getBackupProfiles()
    {
        return Grav::instance()['config']->get('backups.profiles');
    }

    /**
     * @return array
     */
    public static function getPurgeConfig()
    {
        return Grav::instance()['config']->get('backups.purge');
    }

    /**
     * @return array
     */
    public function getBackupNames()
    {
        return array_column(static::getBackupProfiles(), 'name');
    }

    /**
     * @return float|int
     */
    public static function getTotalBackupsSize()
    {
        $backups = static::getAvailableBackups();

        return $backups ? array_sum(array_column($backups, 'size')) : 0;
    }

    /**
     * @param bool $force
     * @return array
     */
    public static function getAvailableBackups($force = false)
    {
        if ($force || null === static::$backups) {
            static::$backups = [];

            $grav = Grav::instance();
            $backups_itr = new GlobIterator(static::$backup_dir . '/*.zip', FilesystemIterator::KEY_AS_FILENAME | \FilesystemIterator::SKIP_DOTS);
            $inflector = $grav['inflector'];
            $long_date_format = DATE_RFC2822;

            /**
             * @var string $name
             * @var SplFileInfo $file
             */
            foreach ($backups_itr as $name => $file) {
                if (preg_match(static::BACKUP_FILENAME_REGEXZ, $name, $matches)) {
                    $date = DateTime::createFromFormat(static::BACKUP_DATE_FORMAT, $matches[2]);
                    $timestamp = $date->getTimestamp();
                    $backup = new stdClass();
                    $backup->title = $inflector->titleize($matches[1]);
                    $backup->time = $date;
                    $backup->date = $date->format($long_date_format);
                    $backup->filename = $name;
                    $backup->path = $file->getPathname();
                    $backup->size = $file->getSize();
                    static::$backups[$timestamp] = $backup;
                }
            }
            // Reverse Key Sort to get in reverse date order
            krsort(static::$backups);
        }

        return static::$backups;
    }

    /**
     * Backup
     *
     * @param int $id
     * @param callable|null $status
     * @param string|null $environment Optional environment to load config from
     * @return string|null
     */
    public static function backup($id = 0, ?callable $status = null, ?string $environment = null)
    {
        $grav = Grav::instance();

        // If environment is specified and different from current, reload config
        if ($environment && $environment !== $grav['config']->get('setup.environment')) {
            $grav->setup($environment);
            $grav['config']->reload();
        }

        $profiles = static::getBackupProfiles();
        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        if (isset($profiles[$id])) {
            $backup = (object) $profiles[$id];
        } else {
            throw new RuntimeException('No backups defined...');
        }

        $name = $grav['inflector']->underscorize($backup->name);
        $date = date(static::BACKUP_DATE_FORMAT, time());
        $filename = trim((string) $name, '_') . '--' . $date . '.zip';
        $destination = static::$backup_dir . DS . $filename;
        $max_execution_time = ini_set('max_execution_time', '600');
        $backup_root = $backup->root;

        if ($locator->isStream($backup_root)) {
            $backup_root = $locator->findResource($backup_root);
        } else {
            $backup_root = rtrim(GRAV_ROOT . $backup_root, DS) ?: DS;
        }

        if (!$backup_root || !file_exists($backup_root)) {
            throw new RuntimeException("Backup location: {$backup_root} does not exist...");
        }

        // Security: Resolve real path and ensure it's within GRAV_ROOT to prevent path traversal
        $realBackupRoot = realpath($backup_root);
        $realGravRoot = realpath(GRAV_ROOT);

        if ($realBackupRoot === false || $realGravRoot === false) {
            throw new RuntimeException("Invalid backup location: {$backup_root}");
        }

        // Check if backup root is within GRAV_ROOT
        $isWithinGravRoot = strpos($realBackupRoot, $realGravRoot) === 0;

        // Only apply blocklist to paths outside GRAV_ROOT to prevent backing up system directories
        // This allows backups within Grav installations under /var/www while still blocking /var/log, etc.
        if (!$isWithinGravRoot) {
            $blockedPaths = ['/etc', '/root', '/home', '/var', '/usr', '/bin', '/sbin', '/tmp', '/proc', '/sys', '/dev'];
            foreach ($blockedPaths as $blocked) {
                if (strpos($realBackupRoot, $blocked) === 0) {
                    throw new RuntimeException("Backup location not allowed: {$backup_root}");
                }
            }
        }

        $backup_root = $realBackupRoot;

        $options = [
            'exclude_files' => static::convertExclude($backup->exclude_files ?? ''),
            'exclude_paths' => static::convertExclude($backup->exclude_paths ?? ''),
        ];

        $archiver = Archiver::create('zip');
        $archiver->setArchive($destination)->setOptions($options)->compress($backup_root, $status)->addEmptyFolders($options['exclude_paths'], $status);

        $status && $status([
            'type' => 'message',
            'message' => 'Done...',
        ]);

        $status && $status([
            'type' => 'progress',
            'complete' => true
        ]);

        if ($max_execution_time !== false) {
            ini_set('max_execution_time', $max_execution_time);
        }

        // Log the backup
        $grav['log']->notice('Backup Created: ' . $destination);

        // Fire Finished event
        $grav->fireEvent('onBackupFinished', new Event(['backup' => $destination]));

        // Purge anything required
        static::purge();

        // Log
        $log = JsonFile::instance($locator->findResource("log://backup.log", true, true));
        $log->content([
            'time'     => time(),
            'location' => $destination
        ]);
        $log->save();

        return $destination;
    }

    /**
     * @return void
     * @throws Exception
     */
    public static function purge()
    {
        $purge_config = static::getPurgeConfig();
        $trigger = $purge_config['trigger'];
        $backups = static::getAvailableBackups(true);

        switch ($trigger) {
            case 'number':
                $backups_count = count($backups);
                if ($backups_count > $purge_config['max_backups_count']) {
                    $last = end($backups);
                    unlink($last->path);
                    static::purge();
                }
                break;

            case 'time':
                $last = end($backups);
                $now = new DateTime();
                $interval = $now->diff($last->time);
                if ($interval->days > $purge_config['max_backups_time']) {
                    unlink($last->path);
                    static::purge();
                }
                break;

            default:
                $used_space = static::getTotalBackupsSize();
                $max_space = $purge_config['max_backups_space'] * 1024 * 1024 *  1024;
                if ($used_space > $max_space) {
                    $last = end($backups);
                    unlink($last->path);
                    static::purge();
                }
                break;
        }
    }

    /**
     * @param string $exclude
     * @return array
     */
    protected static function convertExclude($exclude)
    {
        // Split by newlines, commas, or multiple spaces
        $lines = preg_split("/[\r\n,]+|[\s]{2,}/", $exclude);
        // Remove empty values and trim
        $lines = array_filter(array_map('trim', $lines));

        return array_map('trim', $lines, array_fill(0, count($lines), '/'));
    }
}
