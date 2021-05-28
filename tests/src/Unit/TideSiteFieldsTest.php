<?php

namespace Drupal\Tests\tide_site\Unit;

use Drupal\tide_site\TideSiteFields;

/**
 * Tests for TideSiteFieldsTest class.
 *
 * @coversDefaultClass Drupal\tide_site\TideSiteFields
 * @group tide
 */
class TideSiteFieldsTest extends TideSiteTest {

  /**
   * @covers ::normaliseFieldName
   * @dataProvider providerNormaliseFieldName
   */
  public function testToMachineName($field_name, $entity_type_id, $bundle, $expected) {
    $actual = TideSiteFields::normaliseFieldName($field_name, $entity_type_id, $bundle);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider to test normaliseFieldName() method.
   */
  public function providerNormaliseFieldName() {
    return [
      ['field_ENTITY_TYPE_site', 'node', 'page', 'field_node_site'],
      ['field_ENTITY_TYPE_site', 'entity_with_underscores', 'page', 'field_entity_with_underscores_site'],
      ['field_ENTITY_TYPE_BUNDLE_site', 'node', 'page', 'field_node_page_site'],
      ['field_ENTITY_TYPE_BUNDLE_site', 'entity_with_underscores', 'bundle_with_underscores', 'field_entity_with_underscores_bundle_with_underscores_site'],
      ['field_site', 'entity_with_underscores', 'bundle_with_underscores', 'field_site'],
      ['field_ENTITY_TYPE_site', '', '', 'field_site'],
    ];
  }

  /**
   * @covers ::isSiteField
   * @dataProvider providerIsSiteField
   */
  public function testIsSiteField($field_name, $field_name_generic, $expected) {
    $actual = TideSiteFields::isSiteField($field_name, $field_name_generic);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider to test normaliseFieldName() method.
   */
  public function providerIsSiteField() {
    return [
      // No filtering by generic field.
      ['', NULL, FALSE],
      ['string', NULL, FALSE],
      ['field', NULL, FALSE],
      ['site', NULL, FALSE],
      ['fieldsite', NULL, FALSE],
      ['field_site1', NULL, FALSE],
      ['1field_site', NULL, FALSE],
      ['1field_site1', NULL, FALSE],
      ['field_site', NULL, FALSE],
      ['field_primary_site', NULL, TRUE],
      ['field_node_site', NULL, TRUE],
      ['field_node_primary_site', NULL, TRUE],
      ['field_media_site', NULL, TRUE],
      ['field_media_other_site', NULL, TRUE],

      // Filter by generic field.
      ['', NULL, FALSE],
      ['string', TideSiteFields::FIELD_SITE, FALSE],
      ['field', TideSiteFields::FIELD_SITE, FALSE],
      ['site', TideSiteFields::FIELD_SITE, FALSE],
      ['fieldsite', TideSiteFields::FIELD_SITE, FALSE],
      ['field_site1', TideSiteFields::FIELD_SITE, FALSE],
      ['1field_site', TideSiteFields::FIELD_SITE, FALSE],
      ['1field_site1', TideSiteFields::FIELD_SITE, FALSE],

      // Filter by correct and incorrect existing fields.
      ['field_node_site', TideSiteFields::FIELD_SITE, TRUE],
      ['field_node_primary_site', TideSiteFields::FIELD_SITE, FALSE],
      ['field_node_primary_site', TideSiteFields::FIELD_PRIMARY_SITE, TRUE],
      ['field_node_site', TideSiteFields::FIELD_PRIMARY_SITE, FALSE],
      // Filter by correct and incorrect non-existing fields.
      ['field_node_site', 'other_field', FALSE],
      ['field_node_site', 'other_field_site', FALSE],
    ];
  }

}
