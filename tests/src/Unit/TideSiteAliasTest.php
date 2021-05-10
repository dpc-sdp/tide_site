<?php


namespace Drupal\Tests\tide_site\Unit;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\UnitTestCase;
use Drupal\tide_site\AliasManager;
use Drupal\tide_site\AliasStorageHelper;
use Drupal\tide_site\TideSiteHelper;

class TideSiteAliasTest extends UnitTestCase {

  /**
   * Path alias manager.
   * @var \Drupal\tide_site\AliasManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $aliasManager;

  /**
   * Entity type manager.
   * @var \Drupal\Core\Entity\EntityTypeManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Path alias helper function.
   * @var \Drupal\tide_site\AliasStorageHelper|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $aliasHelper;


  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();
    // Mock out services and entities.
    $this->aliasManager = $this->createMock(AliasManager::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
    $methods = get_class_methods('Drupal\tide_site\AliasStorageHelper');
    unset($methods[array_search('getPathAliasWithoutSitePrefix', $methods)]);
    $this->aliasHelper = $this->getMockBuilder('Drupal\tide_site\AliasStorageHelper')
      ->disableOriginalConstructor()
      ->setMethods($methods)
      ->getMock();
    $alias_matrix = [
      ['/node/1', NULL, '/site-1/hello'],
      ['/node/2', NULL, '/site-2/hello'],
      ['/node/3', NULL, '/site-3/hello'],
      ['/node/4', NULL, '/site-4/hello'],
      ['/node/5', NULL, '/site-5/hello'],
      ['/node/6', NULL, '/site-6/hello'],
    ];
    $this->aliasManager->expects($this->any())
      ->method('getAliasByPath')
      ->will($this->returnValueMap($alias_matrix));
  }

  function testUniquenessOfAlias() {
    $this->assertEquals('/site-1/hello', $this->aliasManager->getAliasByPath('/node/1'));
    $this->assertEquals('/site-2/hello', $this->aliasManager->getAliasByPath('/node/2'));
  }

  /**
   * @dataProvider providerAliasWithoutSite
   */
  function testgetPathAliasWithoutSitePrefix($alias_with_site, $expection) {
    $without_siteid = $this->aliasHelper->getPathAliasWithoutSitePrefix($alias_with_site);
    $this->assertEquals($expection, $without_siteid);
  }

  function providerAliasWithoutSite() {
    return [
      [['alias' => '/site-1/hello'], '/hello'],
      [['alias' => '/site-2/hello_1'], '/hello_1'],
      [['alias' => '/site-3/hello_2'], '/hello_2'],
      [['alias' => '/site-4/hello_3'], '/hello_3'],
      [['alias' => '/site-5/hello_4'], '/hello_4'],
      [['alias' => '/hello_5'], '/hello_5'],
      [['alias' => '/node/1'], '/node/1'],
    ];
  }

}