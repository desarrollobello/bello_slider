<?php

namespace Drupal\bello_slider\Service;

use Drupal\Core\Database\Connection;

/**
 * Service description.
 */
class BelloSlideData {
  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Method description.
   */
  public function getListSlide(): array {
    return $this->database->select(
      'bello_slider_data_slider',
      'bs'
    )->fields('bs')
      ->orderBy('weight', 'ASC')
      ->execute()
      ->fetchAll();
  }

  public function getActiveListSlide(): array {
    return $this->database->select(
      'bello_slider_data_slider',
      'bs'
    )->fields('bs')
      ->orderBy('weight', 'ASC')
      ->condition('status', 1, '=')
      ->execute()
      ->fetchAll();
  }
}
