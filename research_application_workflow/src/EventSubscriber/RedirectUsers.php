<?php

namespace Drupal\research_application_workflow\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 *  Users redirection per role.
 */
class RedirectUsers implements EventSubscriberInterface {

  /*
   * Prevent candidates to access node add pages after.
   * Creating application related nodes.
   */

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $accountProxy;

  /**
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountProxyInterface $account_proxy
   *   The current user.
   */
  public function __construct(
    RouteMatchInterface $route_match,
    AccountProxyInterface $account_proxy,
  ) {
    $this->routeMatch = $route_match;
    $this->accountProxy = $account_proxy;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The event.
   */
  public function doCandidatoRedirect(RequestEvent $event) {

    // Get current user.
    $user = \Drupal::currentUser();

    // Get current user roles.
    $roles = $user->getRoles();

    // Check if user is viewing a node add page
    // and is a candidato.
    if (($this->routeMatch->getRouteName() == 'node.add')
          && (in_array('candidato', $roles))) {
      // Get the node type of the node add page.
      $node_type = $this->routeMatch->getRawParameter('node_type');
      // Redirect candidato to the his node page if exists.
      $this->setResponsePerNid($event, $node_type, $user);
    }
  }

  /**
   * {@inheritdoc}
   */
  /**
   * Get node ids per node type created by the current user.
   */
  public function getNodeIdPerType($node_type, $user) {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('uid', $user->id())
      ->condition('type', $node_type);
    $nid = $query->execute();
    return $nid;
  }

  /**
   * {@inheritdoc}
   */
  /**
   * Set the redirect per nid.
   */
  public function setResponsePerNid($event, $node_type, $user) {
    $nid = $this->getNodeIdPerType($node_type, $user);
    if (count($nid) > 0) {
      $url = Url::fromRoute('entity.node.canonical',
        ['node' => reset($nid)])->toString();
      $redirect = new RedirectResponse($url);

      // Set the redirect on the event, cancelling default response.
      $event->SetResponse($redirect);
    }
    else {
      return;
    }
  }

  /**
   * {@inheritdoc}
   * Create an event subscriber.
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['doCandidatoRedirect', 28],
    ];
  }

}
