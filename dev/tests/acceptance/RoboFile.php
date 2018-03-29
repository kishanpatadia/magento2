<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Symfony\Component\Yaml\Yaml;

/** This is project's console commands configuration for Robo task runner.
 *
 * @codingStandardsIgnoreStart
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
    use Robo\Task\Base\loadShortcuts;

    public function __construct()
    {
        require 'tests'. DIRECTORY_SEPARATOR . 'functional' . DIRECTORY_SEPARATOR . '_bootstrap.php';
        define('VENDOR_BIN_PATH', PROJECT_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR);

    }
    /**
     * Duplicate the Example configuration files used to customize the Project for customization.
     *
     * @return void
     */
    function cloneFiles()
    {
        $this->_exec('cp -vn .env.example .env');
        $this->_exec('cp -vf codeception.dist.yml codeception.yml');
        $this->_exec('cp -vf tests'. DIRECTORY_SEPARATOR .'functional.suite.dist.yml tests'. DIRECTORY_SEPARATOR .'functional.suite.yml');
    }

    /**
     * Finds relative paths between codeception.yml file and MFTF path, and overwrites the default paths.
     *
     * @return void
     */
    private function buildCodeceptionPaths()
    {
        $relativePathFunc = function ($from, $to)
        {
            $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
            $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
            $from = str_replace('\\', '/', $from);
            $to   = str_replace('\\', '/', $to);

            $from     = explode('/', $from);
            $to       = explode('/', $to);
            $relPath  = $to;

            foreach($from as $depth => $dir) {
                // find first non-matching dir
                if($dir === $to[$depth]) {
                    // ignore this directory
                    array_shift($relPath);
                } else {
                    // get number of remaining dirs to $from
                    $remaining = count($from) - $depth;
                    if($remaining > 1) {
                        // add traversals up to first matching dir
                        $padLength = (count($relPath) + $remaining - 1) * -1;
                        $relPath = array_pad($relPath, $padLength, '..');
                        break;
                    } else {
                        $relPath[0] = './' . $relPath[0];
                    }
                }
            }
            return implode('/', $relPath);
        };

        //Find travel path from codeception.yml to FW_BP
        $configYmlPath = dirname(dirname(TESTS_BP)) . DIRECTORY_SEPARATOR;
        $relativePath = call_user_func($relativePathFunc, $configYmlPath, FW_BP);
        $configYmlFile = $configYmlPath . "codeception.yml";
        $defaultConfigYmlFile = $configYmlPath . "codeception.dist.yml";

        if (file_exists($configYmlFile)) {
            $ymlContents = file_get_contents($configYmlFile);
        } else {
            $ymlContents = file_get_contents($defaultConfigYmlFile);
        }
        $ymlArray = Yaml::parse($ymlContents) ?? [];
        if (!array_key_exists("paths", $ymlArray)) {
            $ymlArray["paths"] = [];
        }
        $ymlArray["paths"]["support"] = $relativePath . 'src/Magento/FunctionalTestingFramework';
        $ymlArray["paths"]["envs"] = $relativePath . 'etc/_envs';
        $ymlText = Yaml::dump($ymlArray, 10);
        file_put_contents($configYmlFile, $ymlText);
    }

    /**
     * Duplicate the Example configuration files for the Project.
     * Build the Codeception project.
     *
     * @return void
     */
    function buildProject()
    {
        $this->cloneFiles();
        $this->buildCodeceptionPaths();
        $this->_exec(VENDOR_BIN_PATH .'codecept build');
    }

    /**
     * Generate all Tests in PHP OR Generate set of tests via passing array of tests
     *
     * @param array $tests
     * @param array $opts
     * @return void
     */

    function generateTests(array $tests, $opts = ['config' => null, 'force' => false, 'nodes' => null, 'lines' => null])
    {
        $GLOBALS['GENERATE_TESTS'] = true;
        require 'tests'. DIRECTORY_SEPARATOR . 'functional' . DIRECTORY_SEPARATOR . '_bootstrap.php';
        $testsObjects = [];
        if (!empty($tests))
        {
            foreach ($tests as $test)
            {
                $testsObjects[] = Magento\FunctionalTestingFramework\Test\Handlers\TestObjectHandler::getInstance()->getObject($test);
            }
        }

        if ($opts['force'])
        {
            $GLOBALS['FORCE_PHP_GENERATE'] = true;
        }

        // maintain backwards compatability for devops
        $lines = $opts['lines'] ?? $opts ['nodes'];

        $testsReferencedInSuites = \Magento\FunctionalTestingFramework\Suite\SuiteGenerator::getInstance()->getTestsReferencedInSuites();
        $testManifest = \Magento\FunctionalTestingFramework\Util\TestGenerator::getInstance(null, $testsObjects)->createAllTestFiles($opts['config'], $lines, $testsReferencedInSuites);
        \Magento\FunctionalTestingFramework\Suite\SuiteGenerator::getInstance()->generateAllSuites($testManifest);

        $this->say("Generate Tests Command Run");
    }

    /**
     * Generate a suite based on name(s) passed in as args.
     *
     * @param array $args
     * @throws Exception
     * @return void
     */
    function generateSuite(array $args)
    {
        if (empty($args)) {
            throw new Exception("Please provide suite name(s) after generate:suite command");
        }

        $sg = \Magento\FunctionalTestingFramework\Suite\SuiteGenerator::getInstance();

        foreach ($args as $arg) {
            $sg->generateSuite($arg);
        }
    }

    /**
     * Run all Functional tests.
     *
     * @return void
     */
    function functional()
    {
        $this->_exec(VENDOR_BIN_PATH . 'codecept run functional --skip-group skip');
    }

    /**
     * Run all Tests with the specified @group tag, excluding @group 'skip'.
     *
     * @param string $args
     * @return void
     */
    function group($args = '')
    {
        $this->taskExec(VENDOR_BIN_PATH . 'codecept run functional --verbose --steps --skip-group skip --group')->args($args)->run();
    }

    /**
     * Run all Functional tests located under the Directory Path provided.
     *
     * @param string $args
     * @return void
     */
    function folder($args = '')
    {
        $this->taskExec(VENDOR_BIN_PATH . 'codecept run functional')->args($args)->run();
    }

    /**
     * Run all Tests marked with the @group tag 'example'.
     *
     * @return void
     */
    function example()
    {
        $this->_exec(VENDOR_BIN_PATH . 'codecept run --group example --skip-group skip');
    }

    /**
     * Generate the HTML for the Allure report based on the Test XML output - Allure v1.4.X
     *
     * @return \Robo\Result
     */
    function allure1Generate()
    {
        return $this->_exec('allure generate tests'. DIRECTORY_SEPARATOR .'_output'. DIRECTORY_SEPARATOR .'allure-results'. DIRECTORY_SEPARATOR .' -o tests'. DIRECTORY_SEPARATOR .'_output'. DIRECTORY_SEPARATOR .'allure-report'. DIRECTORY_SEPARATOR .'');
    }

    /**
     * Generate the HTML for the Allure report based on the Test XML output - Allure v2.3.X
     *
     * @return \Robo\Result
     */
    function allure2Generate()
    {
        return $this->_exec('allure generate tests'. DIRECTORY_SEPARATOR .'_output'. DIRECTORY_SEPARATOR .'allure-results'. DIRECTORY_SEPARATOR .' --output tests'. DIRECTORY_SEPARATOR .'_output'. DIRECTORY_SEPARATOR .'allure-report'. DIRECTORY_SEPARATOR .' --clean');
    }

    /**
     * Open the HTML Allure report - Allure v1.4.X
     *
     * @return void
     */
    function allure1Open()
    {
        $this->_exec('allure report open --report-dir tests'. DIRECTORY_SEPARATOR .'_output'. DIRECTORY_SEPARATOR .'allure-report'. DIRECTORY_SEPARATOR .'');
    }

    /**
     * Open the HTML Allure report - Allure v2.3.X
     *
     * @return void
     */
    function allure2Open()
    {
        $this->_exec('allure open --port 0 tests'. DIRECTORY_SEPARATOR .'_output'. DIRECTORY_SEPARATOR .'allure-report'. DIRECTORY_SEPARATOR .'');
    }

    /**
     * Generate and open the HTML Allure report - Allure v1.4.X
     *
     * @return void
     */
    function allure1Report()
    {
        $result1 = $this->allure1Generate();

        if ($result1->wasSuccessful()) {
            $this->allure1Open();
        }
    }

    /**
     * Generate and open the HTML Allure report - Allure v2.3.X
     *
     * @return void
     */
    function allure2Report()
    {
        $result1 = $this->allure2Generate();

        if ($result1->wasSuccessful()) {
            $this->allure2Open();
        }
    }

    /**
     * Run the Pre-Install system check script.
     *
     * @return void
     */
    function preInstall()
    {
        $this->_exec('php pre-install.php');
    }


}
