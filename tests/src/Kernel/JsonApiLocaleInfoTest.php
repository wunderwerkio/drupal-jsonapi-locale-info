<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi_locale_info\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that the locale info is added to the JSON:API response.
 */
class JsonApiLocaleInfoTest extends EntityKernelTestBase {

  use PathAliasTestTrait;

  /**
   * Static UUIDs to use in testing.
   *
   * @var array
   */
  protected static $nodeUuid = [
    1 => '83bc47ad-2c58-45e3-9136-abcdef111111',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi',
    'jsonapi_extras',
    'jsonapi_locale_info',
    'field',
    'node',
    'serialization',
    'system',
    'taxonomy',
    'text',
    'filter',
    'user',
    'file',
    'image',
    'jsonapi_test_normalizers_kernel',
    'jsonapi_test_resource_type_building',
    'content_translation',
    'path',
    'path_alias',
    'language',
  ];


  /**
   * The node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    // Add the additional table schemas.
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['language']);

    // Add languages.
    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();

    $config = $this->config('language.negotiation');
    $config->set('url.prefixes', [
      'en' => 'en',
      'de' => 'de',
    ])->save();

    \Drupal::service('kernel')->rebuildContainer();
    \Drupal::service('router.builder')->rebuild();

    // Ensure we are building a new Language object for each test.
    $this->languageManager = $this->container->get('language_manager');
    $this->languageManager->reset();

    $type = NodeType::create([
      'type' => 'article',
    ]);
    $type->save();

    $manager = $this->container->get('content_translation.manager');
    $manager->setEnabled('node', 'article', TRUE);

    $this->user = $this->drupalCreateUser(['access content']);
    $this->setCurrentUser($this->user);

    $this->httpKernel = $this->container->get('http_kernel');
  }

  /**
   * Test that locale info is added to the response.
   */
  public function testLocaleInfo() {
    // Create nodes and aliases.
    $node = Node::create([
      'title' => 'English Node',
      'type' => 'article',
      'langcode' => 'en',
      'uuid' => static::$nodeUuid[1],
    ]);
    $node->save();

    $node->addTranslation('de', [
      'title' => 'German Node',
      'type' => 'article',
    ])->save();

    $this->createPathAlias('/node/1', '/articles/my-node', 'en');
    $this->createPathAlias('/node/1', '/artikel/meine-node', 'de');

    // Run request.
    $request = Request::create('/jsonapi/node/article', 'GET');
    $request->headers->add(['Content-Type' => 'application/vnd.api+json']);

    $response = $this->httpKernel->handle($request);
    $data = json_decode($response->getContent(), TRUE);

    $this->assertEquals([
      'localeInfo' => [
        ['langcode' => 'de', 'path' => '/artikel/meine-node'],
      ],
    ], $data['data'][0]['meta']);

    // Run request for de.
    $request = Request::create('/de/jsonapi/node/article', 'GET');
    $request->headers->add(['Content-Type' => 'application/vnd.api+json']);

    $response = $this->httpKernel->handle($request);
    $data = json_decode($response->getContent(), TRUE);

    $this->assertEquals([
      'localeInfo' => [
        ['langcode' => 'en', 'path' => '/articles/my-node'],
      ],
    ], $data['data'][0]['meta']);
  }

  /**
   * Creates an instance of the subject under test.
   *
   * @return \Drupal\jsonapi\Controller\EntityResource
   *   An EntityResource instance.
   */
  protected function createEntityResource() {
    return $this->container->get('jsonapi.entity_resource');
  }

}
