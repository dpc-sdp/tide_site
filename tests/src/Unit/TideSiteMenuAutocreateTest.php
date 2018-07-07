<?php

namespace Drupal\Tests\tide_site\Unit;

/**
 * Tests for TideSiteMenuAutocreate class.
 *
 * @coversDefaultClass Drupal\tide_site\TideSiteMenuAutocreate
 * @group tide
 */
class TideSiteMenuAutocreateTest extends TideSiteTest {

  /**
   * @covers ::toMachineName
   * @dataProvider providerToMachineName
   */
  public function testToMachineName($input, $delimiter, $expected) {
    $mock = self::createMock('Drupal\tide_site\TideSiteMenuAutocreate');
    $actual = $this->callProtectedMethod($mock, 'toMachineName', [$input, $delimiter]);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider to test toMachineName() method.
   */
  public function providerToMachineName() {
    return [
      ['', '_', ''],
      ['abc', '_', 'abc'],
      ['123', '_', '123'],
      ['abc123', '_', 'abc123'],
      ['abc 123', '_', 'abc_123'],
      ['abc-123', '_', 'abc_123'],
      ['abc_123', '_', 'abc_123'],
      ['abc_123 def', '_', 'abc_123_def'],
      ['[abc] 123 def', '_', 'abc_123_def'],
      ['**[abc] 123 def  ', '_', 'abc_123_def'],
      ['**[abc] 123 def  ', '-', 'abc-123-def'],
    ];
  }

  /**
   * @covers ::makeMenuLabel
   * @dataProvider providerMakeMenuLabel
   */
  public function testMakeMenuLabel($menu_title, $parents, $expected) {
    $mock = self::prepareMock('Drupal\tide_site\TideSiteMenuAutocreate', [
      'loadTermParents' => $this->prepareMockTermParents($parents),
    ]);
    $actual = $this->callProtectedMethod($mock, 'makeMenuLabel', [$menu_title, NULL]);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider to test makeMenuLabel() method.
   */
  public function providerMakeMenuLabel() {
    return [
      ['', [], ''],
      ['abc', [], 'abc'],
      ['abc', ['t1'], 'abc - t1'],
      ['abc', ['t1', 'p1'], 'abc - p1 - t1'],
      ['abc', ['t1', 'p1', 'p2'], 'abc - p2 - p1 - t1'],
    ];
  }

  /**
   * @covers ::makeMenuName
   * @dataProvider providerMakeMenuName
   */
  public function testMakeMenuName($menu_title, $parents, $expected) {
    $mock = self::prepareMock('Drupal\tide_site\TideSiteMenuAutocreate', [
      'loadTermParents' => $this->prepareMockTermParents($parents),
    ]);
    $actual = $this->callProtectedMethod($mock, 'makeMenuName', [$menu_title, NULL]);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider to test makeMenuName() method.
   */
  public function providerMakeMenuName() {
    return [
      ['abc', [], 'site-abc'],
      ['abc', ['t1'], 'site-abc-t1'],
      ['abc', ['t1', 'p1'], 'site-abc-p1-t1'],
      ['abc', ['t1', 'p1', 'p2'], 'site-abc-p2-p1-t1'],
      ['abc', ['t1', 'p1 p11', 'p2 p21'], 'site-abc-p2-p21-p1-p11-t1'],
    ];
  }

}
