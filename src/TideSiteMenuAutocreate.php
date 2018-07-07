<?php

namespace Drupal\tide_site;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\system\Entity\Menu;

/**
 * Class TideSiteMenuAutocreate.
 *
 * @package Drupal\tide_site.
 */
class TideSiteMenuAutocreate {

  use StringTranslationTrait;

  /**
   * Prefix of the site fields.
   */
  const SITE_FIELD_PREFIX = 'field_site_';

  /**
   * Prefix to add for each generated menu.
   */
  const SITE_MENU_PREFIX = 'site-';

  /**
   * Prefix for the autocreate form element.
   */
  const AUTOCREATE_FIELD_PREFIX = 'autocreate_';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translator
   *   String translator.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $translator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $translator;
  }

  /**
   * Alter form fields to add UI elements to allow menu auto creation.
   *
   * @param array $form
   *   Form array.
   * @param array $field_names
   *   Array of field names.
   *
   * @return array
   *   Array of altered fields.
   */
  public function alterFormFields(array &$form, array $field_names) {
    $tid = $form['tid']['#value'];

    $altered_fields = [];
    foreach ($field_names as $field_name) {
      if (!isset($form[$field_name])) {
        continue;
      }

      $field_title = $form[$field_name]['widget']['#title'];
      if (!$this->canAutocreate($field_title, $tid)) {
        continue;
      }

      $new_field_name = self::makeAutocreateFieldName($field_title);
      $form[$new_field_name] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Automatically create and assign a @menu menu', ['@menu' => $field_title]),
        '#description' => $this->t('Check this box to automatically create a dedicated menu for this site/section when this page is saved. Once created, the menu will be assigned to the %field_title field above.', ['%field_title' => $field_title]),
        '#weight' => $form[$field_name]['#weight'] + 0.1,
        '#default_value' => $this->getDefaultState($tid),
      ];
      $altered_fields[] = $field_name;
    }

    return $altered_fields;
  }

  /**
   * Process submitted form values.
   *
   * @param array $form
   *   Form array.
   * @param array $values
   *   Array of submitted values.
   *
   * @return array
   *   Array of arrays of processed result messages, keyed by 'status' and
   *   'error'.
   */
  public function processFormValues(array $form, array $values) {
    $messages = [
      'status' => [],
      'error' => [],
    ];

    $tid = $values['tid'];

    // Retrieve autocreate fields.
    $field_names = self::filterAutocreateFieldNames($values);
    foreach ($field_names as $field_name => $field_value) {
      if ($field_value) {
        try {
          $menu_name = self::extractMenuName($field_name);
          $assignee_field_name = self::SITE_FIELD_PREFIX . $menu_name;
          $assignee_field_label = $form[$assignee_field_name]['widget']['#title'];

          // Create menu from the label of the relevant field and current term
          // (with hierarchy).
          /** @var \Drupal\Core\Entity\EntityInterface $menu */
          $menu = $this->createMenu($assignee_field_label, $tid);

          // Assign menu to the field.
          $term = $this->loadTerm($tid);
          $term->set($assignee_field_name, [['target_id' => $menu->id()]]);
          $term->save();

          $messages['status'][] = $this->t('Automatically created <a href="@menu_link">%menu_title</a> menu and assigned to @field_label field.', [
            '@menu_link' => $menu->toUrl()->toString(),
            '%menu_title' => $menu->label(),
            '@field_label' => $assignee_field_label,
          ]);
        }
        catch (\Exception $exception) {
          $messages['error'][] = $this->t('Unable to automatically create a menu @menu.', ['@menu' => $menu_name]);
          watchdog_exception('tide_site', $exception);
        }
      }
    }

    return $messages;
  }

  /**
   * Automatically create menu based on provided title and site.
   *
   * @param string $menu_title
   *   Partial menu title to be used in label and machine name.
   * @param int $tid
   *   Sites taxonomy term id.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Created menu entity.
   */
  protected function createMenu($menu_title, $tid) {
    $label = $this->makeMenuLabel($menu_title, $tid);
    $machine_name = $this->makeMenuName($menu_title, $tid);

    $menu = Menu::create([
      'id' => $machine_name,
      'label' => $label,
      'description' => $this->t('Automatically created site menu @label', ['@label' => $label]),
    ]);

    $menu->save();

    return $menu;
  }

  /**
   * Check if menu can be automatically created.
   *
   * @param string $menu_title
   *   Partial menu title to be used in label and machine name.
   * @param int $tid
   *   Sites taxonomy term id.
   *
   * @return bool
   *   TRUE if set of condition matches, FALSE otherwise.
   */
  protected function canAutocreate($menu_title, $tid) {
    // New term.
    if (empty($tid)) {
      return TRUE;
    }

    // Menu already associated.
    $menu_name = $this->getAssociatedMenu($menu_title, $tid);
    if ($menu_name) {
      return FALSE;
    }

    // Menu already exists.
    $existing_menu = Menu::load($this->makeMenuName($menu_title, $tid));
    if ($existing_menu) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get associated menu name from site term.
   *
   * @param string $menu_title
   *   Menu title.
   * @param int $tid
   *   Term id.
   *
   * @return null|string
   *   Menu name or NULL if no menu is associated with a site term.
   */
  protected function getAssociatedMenu($menu_title, $tid) {
    $term = $this->loadTerm($tid);
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $value */
    $value = $term->get(self::SITE_FIELD_PREFIX . self::toMachineName($menu_title));

    return $value->isEmpty() ? NULL : $value->get(0)->getString();
  }

  /**
   * Filter value to extract fields with autocreate fields.
   */
  protected static function filterAutocreateFieldNames($values) {
    return array_filter($values, function ($k) {
      return Unicode::strpos($k, self::AUTOCREATE_FIELD_PREFIX) === 0 && Unicode::strpos($k, 'menu') !== FALSE;
    }, ARRAY_FILTER_USE_KEY);
  }

  /**
   * Create autocreate field machine name.
   */
  protected static function makeAutocreateFieldName($string) {
    return self::AUTOCREATE_FIELD_PREFIX . self::toMachineName($string);
  }

  /**
   * Extract menu name from provided string.
   *
   * @param string $string
   *   String to extract menu name from.
   *
   * @return string
   *   Menu name.
   *
   * @throws \Exception
   *   If provided string does not contain expected field prefix.
   */
  protected static function extractMenuName($string) {
    if (Unicode::strpos($string, self::AUTOCREATE_FIELD_PREFIX) === FALSE) {
      throw new \Exception('Unable to extract menu name from provided value');
    }

    return Unicode::substr($string, Unicode::strlen(self::AUTOCREATE_FIELD_PREFIX));
  }

  /**
   * Get default state based on the provided site term.
   *
   * @param int $tid
   *   Sites taxonomy term id.
   *
   * @return bool
   *   TRUE if set of condition matches, FALSE otherwise.
   */
  protected function getDefaultState($tid) {
    // Enabled for new terms.
    if (empty($tid)) {
      return TRUE;
    }

    // Make disabled for sections.
    return !$this->isSection($tid);
  }

  /**
   * Check that provided term is site rather then section.
   */
  protected function isSite($tid) {
    return count($this->loadTermParents($tid)) == 1;
  }

  /**
   * Check that provided term is section rather then site.
   */
  protected function isSection($tid) {
    return count($this->loadTermParents($tid)) > 1;
  }

  /**
   * Helper to generate menu machine name from provided menu title and term.
   *
   * Example: 'main_menu_site_example_com'.
   *
   * @param string $menu_title
   *   Partial menu title to be used in label and machine name.
   * @param int $tid
   *   Sites taxonomy term id.
   *
   * @return string
   *   Machine name of the menu, including relevant term hierarchy.
   */
  protected function makeMenuName($menu_title, $tid) {
    $parents = $this->loadTermParents($tid);
    $parents = array_reverse($parents, TRUE);

    $machine_name = self::toMachineName(self::SITE_MENU_PREFIX . $menu_title, '-');
    /** @var \Drupal\taxonomy\Entity\Term $parent */
    foreach ($parents as $parent) {
      $machine_name .= '-' . self::toMachineName($parent->getName(), '-');
    }

    return substr($machine_name, 0, 32);
  }

  /**
   * Helper to generate menu label from provided menu title and term.
   *
   * Example: 'Main menu - example.com - some section'.
   *
   * @param string $menu_title
   *   Partial menu title to be used in label and machine name.
   * @param int $tid
   *   Sites taxonomy term id.
   *
   * @return string
   *   Menu label, including relevant term hierarchy.
   */
  protected function makeMenuLabel($menu_title, $tid) {
    $parents = $this->loadTermParents($tid);
    $parents = array_reverse($parents, TRUE);

    $label = $menu_title;

    /** @var \Drupal\taxonomy\Entity\Term $parent */
    foreach ($parents as $parent) {
      $label .= ' - ' . $parent->getName();
    }

    return $label;
  }

  /**
   * Helper to convert string to machine name-compatible string.
   */
  protected static function toMachineName($string, $delimiter = '_') {
    $string = trim($string);
    $string = Unicode::strtolower($string);
    // Replace spaces and hyphens, preserving existing delimiters.
    $string = str_replace([$delimiter, ' ', '-', '_'], $delimiter, $string);
    // Remove all other non-alphanumeric characters.
    $string = preg_replace('/[^a-z0-9\s\-' . preg_quote($delimiter) . ']/', '', $string);

    return $string;
  }

  /**
   * Helper to load term.
   */
  protected function loadTerm($tid) {
    /** @var \Drupal\taxonomy\TermStorage $storage */
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    return $storage->load($tid);
  }

  /**
   * Helper to load term parents.
   */
  protected function loadTermParents($tid) {
    /** @var \Drupal\taxonomy\TermStorage $storage */
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    return $storage->loadAllParents($tid);
  }

}
