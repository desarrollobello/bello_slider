<?php

namespace Drupal\bello_slider\Form;

use Drupal;
use Drupal\bello_slider\Service\BelloSlideData;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Deslizador Bello form.
 */
class FormBelloSlider extends FormBase {

  protected Connection $database;

  protected AccountInterface $current_user;

  protected FileUsageInterface $file_usage;

  protected BelloSlideData $slider;

  public function __construct(
    Connection $database,
    AccountInterface $current_user,
    FileUsageInterface $file_usage,
    BelloSlideData $slider
  ) {
    $this->database = $database;
    $this->current_user = $current_user;
    $this->file_usage = $file_usage;
    $this->slider = $slider;
  }

  public static function create(ContainerInterface $container): FormBelloSlider|static {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('file.usage'),
      $container->get('bello_slider.bello_slide_data'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bello_slider_form_bello_slider';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state
  ): array {
    $group_class = 'group-order-weight';
    $items = $this->slider->getListSlide();

    // Build table.
    $form['items'] = [
      '#type' => 'table',
      '#caption' => $this->t('Items'),
      '#header' => [
        $this->t('Id'),
        $this->t('Estado'),
        $this->t('Imagen'),
        $this->t('Título'),
        $this->t('Enlace'),
        $this->t('Opacidad'),
        $this->t('Peso de fila'),
      ],
      '#empty' => $this->t('No items.'),
      //      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $group_class,
        ],
      ],
      '#tableselect' => FALSE,
    ];

    // Construcción de filas de tabla
    foreach ($items as $key => $value) {
      $form['items'][$key]['#attributes']['class'][] = 'draggable';
      $form['items'][$key]['#weight'] = $value->weight;
      /**
       * Campos de tabla
       */

      //      Identificador de tabla
      $form['items'][$key]['id'] = [
        '#plain_text' => $value->id,
      ];

      //    Estado del slide, oculto o visible
      $form['items'][$key]['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Activo'),
        '#default_value' => $value->status,
      ];

      //      Campo de carga de imagen
      $form['items'][$key]['image_slide'.$key] = [
        '#type' => 'managed_file',
        '#title' => t('Imagen para deslizador'),
        '#upload_validators' => [
          'file_validate_extensions' => ['jpg jpeg png'],
          'file_validate_is_image' => [],
          'file_validate_size' => [1024 * 1024],
        ],
        '#upload_location' => 'public://image-slide-site-bello',
        '#default_value' => !empty($value->fid) ? [$value->fid] : [],
      ];

      //      Título de slide
      $form['items'][$key]['title'] = [
        '#type' => 'textfield',
        '#title' => t('Título'),
        '#maxlength' => 200,
        '#default_value' => $value->title,
      ];

      //      Enlace opcional de slider
      $form['items'][$key]['link'] = [
        '#type' => 'textfield',
        '#title' => t('Enlace'),
        '#default_value' => $value->link,
      ];

      //      Opacidad de Slide
      $form['items'][$key]['opacity'] = [
        '#type' => 'select',
        '#title' => t('Opacidad'),
        '#default_value' => $value->opacity,
        '#options' => [
          '0' => '0',
          '0.1' => '0.1',
          '0.2' => '0.2',
          '0.3' => '0.3',
          '0.4' => '0.4',
          '0.5' => '0.5',
          '0.6' => '0.6',
          '0.7' => '0.7',
          '0.8' => '0.8',
          '0.9' => '0.9',
          '1' => '1',
        ],
      ];

      //      Peso de fila
      $form['items'][$key]['weight'] = [
        '#type' => 'weight',
        '#title_display' => 'visible',
        '#default_value' => $value->weight,
        '#attributes' => [
          'class' => [
            'group-order-weight',
          ],
        ],
      ];

      //      Identificador de tabla oculto
      $form['items'][$key]['id_hidden'] = [
        '#type' => 'hidden',
        '#default_value' => $value->id,
      ];
    }

    //    Botones de acción. Botón de envío de formulario
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  #[NoReturn] public function validateForm(
    array &$form,
    FormStateInterface $form_state
  ): void {
    $file = Drupal::entityTypeManager()->getStorage('file');
    $items = $form_state->getValue('items');
    foreach ($items as $key => $item) {
      if (isset($item['image_slide'.$key][0])) {
        $file_load = $file->load($item['image_slide'.$key][0]);
        if (!empty($file_load)) {
          $image = \Drupal::service('image.factory')->get($file_load->getFileUri());
          if (!($image->getWidth() == 1900 && $image->getHeight() == 475)) {
            $form_state->setErrorByName(
              'image_slide'.$key,
              "El archivo {$file_load->getFilename()} debe tener una resolución de 1900x475"
            );
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  #[NoReturn] public function submitForm(
    array &$form, FormStateInterface $form_state
  ): void {
    $file = Drupal::entityTypeManager()->getStorage('file');
    $items = $form_state->getValue('items');

    foreach ($items as $key => $value) {
      $id = $value['id_hidden'];
      $fid = $value['image_slide'.$key];
      $title = $value['title'];
      $weight = $value['weight'];
      $link = $value['link'];
      $status = $value['status'];
      $opacity = $value['opacity'];

      if (!empty($value['image_slide'.$key])) {
        $fid = $fid[0];
        $file_load = $file->load($fid);
        $file_load->setPermanent();
        $file_load->save();
      } else {

        $fid = NULL;

        $query = $this->database->select(
          'bello_slider_data_slider', 'bs'
        )->fields('bs', ['fid'])
          ->condition('id', $id)
          ->execute()
          ->fetchAll();

        $fid_db = $query[0]->fid;

        if (!empty($fid_db)) {
          $file_load = $file->load($fid_db);
          $file_load->delete();
        }
      }

      $query = $this->database->update('bello_slider_data_slider')
        ->fields(
          [
            'title' => $title,
            'fid' => $fid,
            'weight' => $weight,
            'status' => $status,
            'link' => $link,
            'opacity' => $opacity,
          ]
        )
        ->condition('id', $id)->execute();

    }
    Drupal::messenger()->addStatus('Deslizador principal actualizado');
  }

}
