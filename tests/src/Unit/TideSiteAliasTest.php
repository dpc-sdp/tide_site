<?php

namespace Drupal\Tests\tide_site\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\tide_site\AliasStorageHelper;
use Drupal\tide_site\TideSiteHelper;

class TideSiteAliasTest extends UnitTestCase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Path alias helper function.
   *
   * @var \Drupal\tide_site\AliasStorageHelper|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $aliasHelper;

  /**
   * Tide site helper.
   *
   * @var \Drupal\tide_site\TideSiteHelper|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $tideSiteHelper;

  /**
   * Entity storage.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|Drupal\Core\Entity\ContentEntityNullStorage
   */
  protected $entityStorage;


  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();
    $methods = get_class_methods('Drupal\Core\Entity\ContentEntityNullStorage');
    $this->entityStorage = $this->getMockBuilder('Drupal\Core\Entity\ContentEntityNullStorage')
      ->disableOriginalConstructor()
      ->setMethods($methods)
      ->getMock();
    $methods = get_class_methods('Drupal\Core\Entity\EntityTypeManager');
    $this->entityTypeManager = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeManager')
      ->disableOriginalConstructor()
      ->setMethods($methods)
      ->getMock();
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($this->entityStorage);
    $this->tideSiteHelper = $this->createMock(TideSiteHelper::class);
    $this->aliasHelper = new AliasStorageHelper($this->tideSiteHelper, $this->entityTypeManager);
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

  /**
   * @dataProvider providerAliasWithoutSite
   */
  function testgetPathAliasWithoutSitePrefixNew($alias_with_site, $expection) {
    $without_siteid = $this->aliasHelper->getPathAliasWithoutSitePrefix($alias_with_site);
    $this->assertEquals($expection, $without_siteid);
  }

  function providerIsPathHasSitePrefix() {
    return [
      ['/site-10/hello-1', TRUE],
      ['/site/hello-1', FALSE],
      ['/10/hello-1', FALSE],
      ['/site-1/site-hello-1', TRUE],
      ['/site-10/site-2/-a1', TRUE],
    ];
  }

  /**
   * @dataProvider providerIsPathHasSitePrefix
   */
  function testIsPathHasSitePrefixNew($alias, $expectation) {
    $methods = get_class_methods('Drupal\path_alias\Entity\PathAlias');
    $path_alias = $this->getMockBuilder('Drupal\path_alias\Entity\PathAlias')
      ->disableOriginalConstructor()
      ->setMethods($methods)
      ->getMock();
    $path_alias->expects($this->any())->method('getAlias')->willReturn($alias);
    $this->assertEquals($expectation, $this->aliasHelper->isPathHasSitePrefix($path_alias));
  }

  function testIsAliasExists() {
    $methods = get_class_methods('Drupal\path_alias\Entity\PathAlias');
    $path_alias = $this->getMockBuilder('Drupal\path_alias\Entity\PathAlias')
      ->disableOriginalConstructor()
      ->setMethods($methods)
      ->getMock();
    $path_alias->expects($this->any())
      ->method('getAlias')
      ->willReturn('/site-1/hello');
    $this->entityStorage->expects($this->any())
      ->method('loadByProperties')
      ->willReturn([$path_alias]);
    $this->assertEquals('/site-1/hello', $this->aliasHelper->isAliasExists('/site-1/hello')
      ->getAlias());
    $this->assertEquals($path_alias, $this->aliasHelper->isAliasExists('/site-1/hello'));
  }

}