<?php

namespace Drupal\ckeditor_templates\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dialog form for allowing users to select a template.
 */
class CKEditorTemplatesDialogForm extends FormBase {

  /**
   * The entity type manager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file url generator instance.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The path for the current module folder.
   *
   * @var string
   */
  protected string $moduleFolder;

  /**
   * The available templates.
   *
   * @var array
   */
  protected array $templates;

  /**
   * The AJAX wrapper id.
   *
   * @var string
   */
  protected $ajaxWrapper = 'ckeditor-template-dialog-form--ajax-wrapper';

  /**
   * Create a new dialog instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager instance.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file url generator instance.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The provider for a list of available modules.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileUrlGeneratorInterface $file_url_generator, ModuleExtensionList $extension_list_module) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->moduleFolder = '/' . $extension_list_module->getPath('ckeditor_templates');
    $this->templates = [];

    // Load the templates.
    $templates = $this->getTemplates();
    foreach ($templates as $format) {
      $this->templates[$format->id()] = [
        'label' => $format->label(),
        'formats' => $format->get('formats') ?? [],
        'thumb' => $this->getThumb($format->get('thumb')[0] ?? ''),
        'description' => $format->get('description') ?? '',
        'code' => $format->get('code') ?? '',
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): CKEditorTemplatesDialogForm | static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
      $container->get('extension.list.module')
    );
  }

  /**
   * Loads the CKEditor Templates.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   A list of CKEditor Template entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getTemplates(): array {
    $storage = $this->entityTypeManager
      ->getStorage('ckeditor_templates');

    $nids = $storage->getQuery()
      ->condition('status', 1)
      ->sort('weight', 'ASC')
      ->execute();

    return $storage->loadMultiple($nids);
  }

  /**
   * Gets the thumbnail for an image.
   *
   * @param string $thumb
   *   The thumb image id.
   *
   * @return string
   *   The thumb image URL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getThumb(string $thumb): string {
    $image = '';

    if (!empty($thumb)) {
      $file = $this->entityTypeManager
        ->getStorage('file')
        ->load($thumb);

      if (isset($file)) {
        $fileUri = $file->getFileUri();

        $style = $this->entityTypeManager
          ->getStorage('image_style')
          ->load('thumbnail');
        if (isset($style)) {
          $image = $style->buildUrl($fileUri) ?? '';
        }
        else {
          $image = $this->fileUrlGenerator->generateAbsoluteString($fileUri) ?? '';
        }
      }
    }

    if (empty($image)) {
      $image = $this->moduleFolder . '/js/ckeditor5_plugins/ckeditor_templates/theme/images/placeholder.svg';
    }

    return $image;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor_templates__dialog_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $editor_id = ''): array {
    $templates = [];

    // Gets the templates.
    foreach ($this->templates as $key => $template) {
      if (in_array($editor_id, $template['formats'])) {
        $templates[$key] = '
            <img src="' . $template['thumb'] . '" alt="' . $template['label'] . '" />
            <div>
              <strong>' . $template['label'] . ' </strong>
              <span>' . $template['description'] . '</span>
            </div>
          ';
      }
    }

    // Gets the editor.
    try {
      $editor = $this->entityTypeManager
        ->getStorage('editor')
        ->load($editor_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger('templates')->critical($e->getMessage());
    }

    // Validate there are templates.
    if (empty($templates) || !isset($editor)) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('There is no template available for the <strong>@formatLabel</strong> text format.', [
          '@formatLabel' => $editor?->label() ?? $editor_id,
        ]),
      ];

      return $form;
    }

    // List the templates.
    $form['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => 'Select the template to open in the editor:',
    ];

    $form['templates'] = [
      '#type' => 'radios',
      '#options' => $templates,
    ];

    $settings = $editor->getSettings();
    $replace_content = $settings['plugins']['ckeditor_templates_plugin']['replace_content'] ?? FALSE;
    $form['replace_content'] = [
      '#title' => $this->t('Replace actual contents'),
      '#type' => 'checkbox',
      '#default_value' => $replace_content,
      '#description' => $this->t('Remove the actual contents, keeping only the selected template.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Insert'),
        '#ajax' => [
          'callback' => [$this, 'ajaxSubmitForm'],
          'wrapper' => $this->ajaxWrapper,
        ],
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback function for inserting HTML code into the CKEditor.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   AJAX response.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state): AjaxResponse | array {
    $response = new AjaxResponse();

    $template = $form_state->getValue('templates');
    if (isset($template)) {
      $htmlCode = $this->templates[$template]['code'] ?? '';
      if (!empty($htmlCode)) {
        $response->addCommand(new EditorDialogSave([
          'htmlCode' => $htmlCode,
          'replace' => $form_state->getValue('replace_content'),
        ]));
      }
    }

    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This function is required by Drupal, but the request is being
    // handled in the ajaxSubmitForm() function.
  }

}
