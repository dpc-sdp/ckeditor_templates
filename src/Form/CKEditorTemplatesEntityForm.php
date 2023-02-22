<?php

namespace Drupal\ckeditor_templates\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Psr\Container\ContainerInterface;

/**
 * CKEditor Template form.
 *
 * @property \Drupal\ckeditor_templates\CKEditorTemplatesInterface $entity
 */
class CKEditorTemplatesEntityForm extends EntityForm {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Gets the allowed format options.
    $allowedFormatOptions = [];
    foreach (filter_formats() as $format) {
      $editor = editor_load($format->id());
      if (isset($editor) && $editor->getEditor() === 'ckeditor5') {
        $allowedFormatOptions[$format->id()] = $format->label();
      }
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ckeditor_templates\Entity\CKEditorTemplates::load',
      ],
      '#disabled' => !$this->entity->isNew(),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
    ];

    $form['thumb'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Illustration Image/Icon'),
      '#default_value' => $this->entity->get('thumb'),
      '#description' => $this->t('Allowed types: png jpeg jpg gif'),
      '#upload_location' => 'public://ckeditor-templates',
      '#upload_validators' => [
        'file_validate_extensions' => ['gif png jpg jpeg'],
      ],
      '#cardinality' => 1,
    ];

    $form['code'] = [
      '#type' => 'textarea',
      '#title' => $this->t('HTML Code'),
      '#default_value' => $this->entity->get('code'),
      '#description' => $this->t('The HTML code to be injected into the CKEditor.'),
      '#required' => TRUE,
    ];

    $form['formats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available For'),
      '#default_value' => $this->entity->get('formats') ?? [],
      '#options' => $allowedFormatOptions,
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $template = $this->entity;
    $status = $template->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label CKEditor Template.', [
        '%label' => $template->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label CKEditor Template was not saved.', [
        '%label' => $template->label(),
      ]));
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $status;
  }

}
