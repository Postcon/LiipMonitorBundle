<?php

namespace Liip\MonitorBundle\Controller;

use Liip\MonitorBundle\Helper\ArrayReporter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Liip\MonitorBundle\Runner;
use Liip\MonitorBundle\Helper\PathHelper;

class HealthCheckController
{
    protected $container;
    protected $runner;
    protected $pathHelper;
    protected $template;

    /**
     * @param ContainerInterface $container
     * @param Runner             $runner
     * @param PathHelper         $pathHelper
     * @param                    $template
     */
    public function __construct(ContainerInterface $container, Runner $runner, PathHelper $pathHelper, $template)
    {
        $this->container = $container;
        $this->runner = $runner;
        $this->pathHelper = $pathHelper;
        $this->template = $template;
    }

    /**
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $urls = $this->pathHelper->getRoutesJs(array(
            'liip_monitor_run_all_checks' => array(),
            'liip_monitor_run_single_check' => array('checkId' => 'replaceme')
        ));

        $css = $this->pathHelper->getStyleTags(array(
            'bundles/liipmonitor/css/bootstrap/css/bootstrap.min.css',
            'bundles/liipmonitor/css/style.css'
        ));

        $javascript = $this->pathHelper->getScriptTags(array(
            'bundles/liipmonitor/javascript/jquery-1.7.1.min.js',
            'bundles/liipmonitor/javascript/ember-0.9.5.min.js',
            'bundles/liipmonitor/javascript/app.js'
        ));

        // this is a hack to make the bundle template agnostic.
        // URL generation for Assets and Routes is still handled by the framework.
        ob_start();
        include $this->template;
        $content = ob_get_clean();

        return new Response($content, 200, array('Content-Type' => 'text/html'));
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Request $request)
    {
        $ret = array();

        $runner = $this->getRunner($request);


        if ($runner) {
            foreach ($runner->getChecks() as $alias => $check) {
                $ret[] = $alias;
            }
        }

        return new JsonResponse($ret);
    }

    /**
     * @param  Request                                    $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function runAllChecksAction(Request $request)
    {
        $report = $this->runTests($request);

        return new JsonResponse(array(
            'checks' => $report->getResults(),
            'globalStatus' => $report->getGlobalStatus()
        ));
    }

    /**
     * @param  Request                                    $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function runAllChecksHttpStatusAction(Request $request)
    {
        $report = $this->runTests($request);

        return new Response(
            '',
            ($report->getGlobalStatus() === ArrayReporter::STATUS_OK ? 200 : 502)
        );
    }

    /**
     * @param  string                                     $checkId
     * @param  Request                                    $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function runSingleCheckHttpStatusAction($checkId, Request $request)
    {
        $report = $this->runTests($request, $checkId);

        return new Response(
            '',
            ($report->getGlobalStatus() === ArrayReporter::STATUS_OK ? 200 : 502)
        );
    }

    /**
     * @param  string                                     $checkId
     * @param  Request                                    $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function runSingleCheckAction($checkId, Request $request)
    {
        $results = $this->runTests($request, $checkId)->getResults();

        return new JsonResponse($results[0]);
    }

    /**
     * @param  Request       $request
     * @param  string|null   $checkId
     * @return ArrayReporter
     */
    protected function runTests(Request $request, $checkId = null)
    {
        $reporters = $request->query->get('reporters', array());

        if (!is_array($reporters)) {
            $reporters = array($reporters);
        }

        $reporter = new ArrayReporter();

        if ($runner = $this->getRunner($request)) {
            $runner->addReporter($reporter);
            $runner->useAdditionalReporters($reporters);
            $runner->run($checkId);
        }

        return $reporter;
    }

    /**
     * @param Request $request
     *
     * @return null|Runner
     */
    private function getRunner(Request $request)
    {
        $group = $request->query->get('group');

        if (!$group) {
            $group = $this->container->getParameter('liip_monitor.default_group');
        }

        $runnerServiceId = 'liip_monitor.runner_' . $group;

        return $this->container->has($runnerServiceId) ? $this->container->get($runnerServiceId) : null;
    }
}
