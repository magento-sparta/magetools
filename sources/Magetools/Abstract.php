<?php

abstract class Magetools_Abstract
{
    protected $_opts = 'h';

    protected $_longOpts = array('help');

    protected $_optsMap = array(
        'help' => 'h'
    );

    protected $_options = array();

    protected $_mageDir = array();

    protected $_colors = array(
        'black'        => '0;30',
        'dark_gray'    => '1;30',
        'blue'         => '0;34',
        'light_blue'   => '1;34',
        'green'        => '0;32',
        'light_green'  => '1;32',
        'cyan'         => '0;36',
        'light_cyan'   => '1;36',
        'red'          => '0;31',
        'light_red'    => '1;31',
        'purple'       => '0;35',
        'light_purple' => '1;35',
        'brown'        => '0;33',
        'yellow'       => '1;33',
        'light_gray'   => '0;37',
        'white'        => '1;37'
    );

    protected $_localXml;

    protected $_version = '1.0.0.0';

    protected $_scriptName;

    protected function _getOptions()
    {
        if (!$this->_options) {
            $this->_options = getopt($this->_opts . 'v', $this->_longOpts);
            $this->_options['default'] = array_pop($_SERVER['argv']);
            // [^\-].*
        }

        return $this->_options;
    }

    protected function _getOpt($longOption, $default = null)
    {
        $options = $this->_getOptions();

        if (isset($options[$longOption])) {
            return $options[$longOption] === false ? true : $options[$longOption];
        }

        $mappedOption = isset($this->_optsMap[$longOption]) ? $this->_optsMap[$longOption] : null;
        if (isset($options[$mappedOption])) {
            return $options[$mappedOption] === false ? true : $options[$mappedOption];
        }

        return $default;
    }

    protected function _validate()
    {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            die('This script cannot be run from Browser. This is the shell script.' . PHP_EOL);
        }
    }

    protected function _showHelp()
    {
        if ($this->_getOpt('help')) {
            die($this->_usageHelp());
        }
    }

    protected function _showVersion()
    {
        $options = $this->_getOptions();
        if (isset($options['v'])) {
            die($this->_scriptName . ' version: ' . $this->_version . PHP_EOL);
        }
    }

    protected function _usageHelp()
    {
        return <<<USAGE
Usage:  php -f script.php -- [options]

  -h            Short alias for help
  help          This help

USAGE;
    }

    protected function _findMageDir($absolutePath)
    {
        if (!$absolutePath || $absolutePath === DS) {
            die('You should run this script inside Magento folder (or its children)' . PHP_EOL);
        }

        if (file_exists($absolutePath . DS . 'app' . DS. 'Mage.php')) {
            return $absolutePath;
        }

        return $this->_findMageDir(realpath($absolutePath . DS . '..' . DS));
    }

    protected function _getMageDir($current = '', $checkExists = true)
    {
        $directory = realpath($this->_mageDir . DS . strtr($current, array('/' => DIRECTORY_SEPARATOR)));

        if ($checkExists && !is_dir($directory)) {
            throw new Exception(sprintf('Directory "%s" is not exists.', $directory));
        }

        return $directory;
    }

    protected function _checkMageDirectory()
    {
        $this->_mageDir = $this->_findMageDir(getcwd());
    }

    public function __construct()
    {
        $this->_validate();
        $this->_showVersion();
        $this->_showHelp();
        $this->_checkMageDirectory();
        return $this;
    }

    abstract public function run();

    protected function _getScriptName() {
        if (!$this->_scriptName) {
            foreach (array('SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF', 'PATH_TRANSLATED') as $name) {
                if (isset($_SERVER[$name])) {
                    $this->_scriptName = basename($_SERVER[$name]);
                    break;
                }
            }
        }

        return $this->_scriptName;
    }

    protected function _printMessage ($message, $die = false)
    {
        if ($die) {
            die($this->_getScriptName() . ': ' . $message . PHP_EOL);
        } else {
            echo $this->_getScriptName() . ': ' . $message . PHP_EOL;
        }
    }

    protected function _getColoredValue($value, $color = 'white')
    {
        return isset($this->_colors[$color]) ? ("\033[" . $this->_colors[$color] . "m" . $value . "\033[0m") : $value;
    }

    /**
     * @param $path
     * @param bool $throwException
     * @return bool|SimpleXMLElement
     * @throws Exception
     */
    protected function _getLocalXml()
    {
        if (is_null($this->_localXml)) {
            $path = $this->_getMageDir('app/etc') . DS . 'local.xml';

            if (!file_exists($path)) {
                $this->_printMessage(sprintf('Cannot find current "%s" file', $path));
            }

            $this->_localXml = simplexml_load_file($path);
            if (!$this->_localXml) {
                $this->_printMessage(sprintf('Declaration XML of "%s" cannot be parsed', $path));
                return false;
            }

            if (!$this->_localXml->xpath('/config/global/resources/default_setup')) {
                $this->_printMessage(sprintf('Node /config/global/resources/default_setup in current "%s" is absent', $path));
                return false;
            }
        }

        return $this->_localXml;
    }

    protected function _loadAppMagePhp()
    {
        spl_autoload_unregister('Magetools_Autoloader');

        $mageFile = $this->_getMageDir('app') . DS . 'Mage.php';

        if (!file_exists($mageFile)) {
            throw new Exception(sprintf('The main file of Magento "%s" is absent', $mageFile));
        }

        if (!is_readable($mageFile)) {
            throw new Exception(sprintf('The main file of Magento "%s" is not readable', $mageFile));
        }

        @require_once $mageFile;

        if (!class_exists('Mage', true)) {
            throw new Exception('Class "Mage" is not found. Maybe it was customized.');
        }
    }
}
