<?php

/**
 * @package     pkg_stealthwatch
 * @author      Damir B
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class ($container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                public function __construct(private DatabaseInterface $db)
                {
                }

                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'uninstall') {
                        return true;
                    }

                    if (version_compare(JVERSION, '5.0.0', '<') || version_compare(PHP_VERSION, '8.1.0', '<')) {
                        $this->message('Stealth Watch requires Joomla 5.0+ and PHP 8.1+.', 'error');

                        return false;
                    }

                    // The earlier "Plugin Watch" test build would log everything twice.
                    if ($this->extensionId('plugin', 'pluginwatch', 'system')) {
                        $this->message(
                            'The old "Plugin Watch" extension is still installed. Please uninstall the "Plugin Watch" package (System &rarr; Manage &rarr; Extensions) so changes are not logged twice.',
                            'warning'
                        );
                    }

                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'uninstall') {
                        return true;
                    }

                    $pluginId    = $this->extensionId('plugin', 'stealthwatch', 'system');
                    $componentId = $this->extensionId('component', 'com_stealthwatch');

                    if ($pluginId && \in_array($type, ['install', 'discover_install'], true)) {
                        $query = $this->db->createQuery()
                            ->update($this->db->quoteName('#__extensions'))
                            ->set($this->db->quoteName('enabled') . ' = 1')
                            ->where($this->db->quoteName('extension_id') . ' = :id')
                            ->bind(':id', $pluginId, ParameterType::INTEGER);
                        $this->db->setQuery($query)->execute();
                    }

                    // Verify the component really got registered (this is what was missing before).
                    if (!$componentId) {
                        $this->message(
                            'Stealth Watch: the component (com_stealthwatch) was NOT registered, so the log screen will be missing. Please send the installer messages shown above.',
                            'error'
                        );

                        return true;
                    }

                    $this->ensureFirstManager($componentId);

                    $query = $this->db->createQuery()
                        ->select('COUNT(*)')
                        ->from($this->db->quoteName('#__menu'))
                        ->where($this->db->quoteName('client_id') . ' = 1')
                        ->where($this->db->quoteName('component_id') . ' = :cid')
                        ->bind(':cid', $componentId, ParameterType::INTEGER);

                    if ((int) $this->db->setQuery($query)->loadResult() === 0) {
                        $this->message(
                            'Stealth Watch is installed, but its admin menu item was not created. You can open it directly: <a href="index.php?option=com_stealthwatch">index.php?option=com_stealthwatch</a>',
                            'warning'
                        );
                    } else {
                        $this->message(
                            'Stealth Watch is active. Open <a href="index.php?option=com_stealthwatch">Components &rarr; Stealth Watch</a>; use Options to manage Settings Managers, protection and retention.'
                        );
                    }

                    return true;
                }

                /**
                 * Make the installing user the first Settings Manager, so the
                 * options are restricted from the start.
                 */
                private function ensureFirstManager(int $componentId): void
                {
                    $user = Factory::getApplication()->getIdentity();

                    if (!$user || !$user->id) {
                        return;
                    }

                    $query = $this->db->createQuery()
                        ->select($this->db->quoteName('params'))
                        ->from($this->db->quoteName('#__extensions'))
                        ->where($this->db->quoteName('extension_id') . ' = :id')
                        ->bind(':id', $componentId, ParameterType::INTEGER);

                    $params = json_decode((string) $this->db->setQuery($query)->loadResult(), true) ?: [];

                    if (!empty($params['managers'])) {
                        return;
                    }

                    $params['managers'] = ['managers0' => ['user_id' => (int) $user->id]];
                    $json               = json_encode($params);

                    $query = $this->db->createQuery()
                        ->update($this->db->quoteName('#__extensions'))
                        ->set($this->db->quoteName('params') . ' = :params')
                        ->where($this->db->quoteName('extension_id') . ' = :id')
                        ->bind(':params', $json)
                        ->bind(':id', $componentId, ParameterType::INTEGER);

                    $this->db->setQuery($query)->execute();

                    $this->message('You (' . htmlspecialchars($user->username) . ') have been added as the first Stealth Watch Settings Manager.');
                }

                private function extensionId(string $type, string $element, string $folder = ''): int
                {
                    $query = $this->db->createQuery()
                        ->select($this->db->quoteName('extension_id'))
                        ->from($this->db->quoteName('#__extensions'))
                        ->where($this->db->quoteName('type') . ' = :type')
                        ->where($this->db->quoteName('element') . ' = :element')
                        ->bind(':type', $type)
                        ->bind(':element', $element);

                    if ($folder !== '') {
                        $query->where($this->db->quoteName('folder') . ' = :folder')->bind(':folder', $folder);
                    }

                    return (int) $this->db->setQuery($query)->loadResult();
                }

                private function message(string $text, string $type = 'message'): void
                {
                    Factory::getApplication()->enqueueMessage($text, $type);
                }
            }
        );
    }
};
