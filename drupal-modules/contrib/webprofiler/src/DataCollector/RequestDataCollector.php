<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector as BaseRequestDataCollector;

/**
 * Integrate _content into the RequestDataCollector.
 */
class RequestDataCollector extends BaseRequestDataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  private $controllerResolver;

  /**
   * @var array
   */
  private $accessCheck;

  /**
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controllerResolver
   */
  public function __construct(ControllerResolverInterface $controllerResolver) {
    parent::__construct();

    $this->controllerResolver = $controllerResolver;
    $this->accessCheck = [];
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    parent::collect($request, $response, $exception);

    if ($controller = $this->controllerResolver->getController($request)) {
      if (is_object($controller)) {
        $class = get_class((object) $controller);
        $method = '__invoke';
      } else {
        $class = $controller[0];
        $method = $controller[1];
      }

      $this->data['controller'] = $this->getMethodData($class, $method);
      $this->data['access_check'] = $this->accessCheck;
    }
  }

  /**
   * @param $service_id
   * @param $callable
   * @param \Symfony\Component\HttpFoundation\Request $request
   */
  public function addAccessCheck($service_id, $callable, Request $request) {
    $this->accessCheck[$request->getPathInfo()][] = [
      'service_id' => $service_id,
      'callable' => $this->getMethodData($callable[0], $callable[1]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Request');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->data['status_code'] . ' ' . $this->data['status_text'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABwAAAAcCAQAAADYBBcfAAACvElEQVR42tVTbUhTYRTerDCnKVoUUr/KCZmypA9Koet0bXNLJ5XazDJ/WFaCUY0pExRZXxYiJgsxWWjkaL+yK+po1gjyR2QfmqWxtBmaBtqWGnabT++c11Fu4l/P4VzOPc95zoHznsNZodIbLDdRcKnc1Bu8DAK45ZsOnykQNMopsNooLxCknb0cDq5vml9FtHiIgpBR0R6iihYyFMTDt2Lg56ObPkI6TMGXSof1EV67IqCwisJSWliFAG/E0CfFIiebdNypcxi/1zgyFiIiZ3sJQr0RQx5frLa6k7SOKRo3oMFNR5t62h2rttKXEOKFqDCxtXNmmBokO2KKTlp3IdWuT2dYRNGKwEXEBCcL172G5FG0aIxC0kR9PBTVH1kkwQn+IqJnCE33EalVzT9GJQS1tAdD3CKicJYFrxqx7W2ejCEdZy1FiC5tZxHhLJKOZaRdQJAyV/YAvDliySALHxmxR4Hqe2iwvaOR/CEuZYJFSgYhVbZRkA8KGdEktrqnqra90NndCdkt77fjIHIhexOrfO6O3bbbOj/rqu5IptgyR3sU93QbOYhquZK4MCDp0Ina/PLsu5JvbCTRaapUdUmIV/RzoMdsk/0hWRNdAvKOmvqlN0drsJbJf1P4YsQ5lGrJeuosiOUgbOC8cto3LfOXTdVd7BqZsQKbse+0jUL6WPcesqs4MNSUTQAxGjwFiC8m3yzmqwHJBWYKBJ9WNqW/dHkpU/osch1Yj5RJfXPfSEe/2UPsN490NPfZG5CKyJmcV5ayHyzy7BMqsXfuHhGK/cjAIeSpR92gehR55D8TcQhDEKJwytBJ4fr4NULvrEM8NszfJPyxDoHYAQ1oPCWmIX4gifmDS/DV2DKeb25FHWr76yEG7/9L4YFPeiQQ4/8LkgJ8Et+NncTCsYqzXAEXa7CWdPZzGWdlyV+vST0JanfPvwAAAABJRU5ErkJggg==';
  }

  /**
   * @return array|string
   */
  public function getData() {
    // Drupal 8.5+ uses Symfony 3.4.x that changes the way the Request data are
    // collected. Data is altered with \Symfony\Component\HttpKernel\DataCollector\DataCollector::cloneVar.
    // The stored data (of type \Symfony\Component\VarDumper\Cloner\Data) is
    // suitable to be converted to a string by a Dumper (\Symfony\Component\VarDumper\Dumper\DataDumperInterface).
    // In our implementation however we need that data as an array, to be later
    // converted in a json response by a REST endpoint. We need to refactor the
    // whole way Web Profiler works to allow that. At the moment we just
    // retrieve the raw Data value and do some string manipulation to clean the
    // output a bit.
    $data = $this->data->getValue(TRUE);
    unset($data['request_attributes']['_route_params']);
    unset($data['request_attributes']['_access_result']);

    $route_object = [];
    foreach ($data['request_attributes']['_route_object'] as $key => $result) {
      $key = str_replace("\0", '', $key);
      $key = str_replace('Symfony\Component\Routing\Route', 'Symfony\Component\Routing\Route::', $key);
      $route_object[$key] = $result;
    }
    $data['request_attributes']['_route_object'] = $route_object;

    return $data;
  }

}
