<?php

/**
 * @file
 * Contains \Drupal\photos_access\Controller\DefaultController.
 */

namespace Drupal\photos_access\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Default controller for the photos_access module.
 */
class DefaultController extends ControllerBase {

  public function photos_access_page(\Drupal\node\NodeInterface $node) {
    if ($node) {
      $pass_form = \Drupal::formBuilder()->getForm('\Drupal\photos_access\Form\PhotosAccessPasswordForm', $node);
      $output = drupal_render($pass_form);
      return array(
        '#markup' => $output
      );
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  public function photos_access_multiple_users_autocomplete($string = '') {
    $array = drupal_explode_tags($string);
    $last_string = trim(array_pop($array));
    $matches = [];

    if ($last_string != '') {
      $result = db_select('users')
        ->fields('users', ['name'])
        ->condition('name', db_like($last_string) . '%', 'LIKE')
        ->range(0, 10)
        ->execute();
      $prefix = count($array) ? implode(', ', $array) . ', ' : '';
      foreach ($result as $user) {
        $n = $user->name;
        $matches[$prefix . $n] = $user->name;
      }
    }

    drupal_json_output($matches);
  }

}
