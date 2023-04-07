<?php

declare(strict_types=1);

namespace Drupal\jsonapi_locale_info\Normalizer;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\jsonapi_extras\Normalizer\ResourceObjectNormalizer as ExtrasResourceObjectNormalizer;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Custom normalizer that adds alternative locale paths to localizable entities.
 */
class ResourceObjectNormalizer extends ExtrasResourceObjectNormalizer {

  /**
   * Constructs a ResourceObjectNormalizer object.
   *
   * @param \Symfony\Component\Serializer\Normalizer\NormalizerInterface $inner
   *   The decorated service.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $contentTranslationManager
   *   The content translation manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
   *   The alias manager.
   */
  public function __construct(
    NormalizerInterface $inner,
    protected ContentTranslationManagerInterface $contentTranslationManager,
    protected LanguageManagerInterface $languageManager,
    protected AliasManagerInterface $aliasManager,
  ) {
    parent::__construct($inner);
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof ResourceObject);
    $cacheable_normalization = parent::normalize($object, $format, $context);
    assert($cacheable_normalization instanceof CacheableNormalization);

    if ($this->decorationApplies($object)) {
      $localeInfo = [];

      foreach ($this->getLocalePaths($object) as $langcode => $data) {
        $alias = $data['alias'];

        $localeInfo[] = [
          'langcode' => $langcode,
          'path' => $alias,
        ];
      }

      return new CacheableNormalization(
        /* $cacheable_normalization->withCacheableDependency( */
        /*   (new CacheableMetadata())->addCacheTags(['path_alias:4']) */
        /* ), */
        $cacheable_normalization,
        static::addLocaleInfoMeta($object, $cacheable_normalization->getNormalization(), $localeInfo),
      );
    }

    return $cacheable_normalization;
  }

  /**
   * Get alias or path to other locales for object.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $resourceObject
   *   The resource object.
   *
   * @return array
   *   Array of paths keyed by langcode.
   */
  protected function getLocalePaths(ResourceObject $resourceObject): array {
    $entityPath = $this->getPathFromResourceObject($resourceObject);
    if (!$entityPath) {
      return [];
    }

    $availableLanguages = $this->languageManager->getLanguages();

    return array_reduce($availableLanguages, function ($carry, $language) use ($resourceObject, $entityPath) {
      // Skip currently active language.
      if ($resourceObject->getLanguage()->getId() === $language->getId()) {
        return $carry;
      }

      $alias = $this->aliasManager->getAliasByPath($entityPath, $language->getId());

      $carry[$language->getId()] = [
        'alias' => $alias,
      ];

      return $carry;
    }, []);
  }

  /**
   * Determines if the decoration applies to the given resource object.
   *
   * A valid resource object is one that has a path field and is translatable.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $resourceObject
   *   The resource object.
   */
  protected function decorationApplies(ResourceObject $resourceObject): bool {
    $resourceType = $resourceObject->getResourceType();
    $entityType = $resourceType->getEntityTypeId();
    $bundle = $resourceType->getBundle();

    if (!$resourceType->hasField('path')) {
      return FALSE;
    }

    return $this->contentTranslationManager->isEnabled($entityType, $bundle);
  }

  /**
   * Gets the path from a resource object.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $resourceObject
   *   The resource object.
   */
  protected function getPathFromResourceObject(ResourceObject $resourceObject): ?string {
    if ($resourceObject->hasField('drupal_internal__nid')) {
      $id = $resourceObject->getField('drupal_internal__nid')->getString();
    }
    elseif ($resourceObject->hasField('id')) {
      $id = $resourceObject->getField('id')->getString();
    }
    else {
      return NULL;
    }

    $entityTypeId = explode('--', $resourceObject->getTypeName())[0];

    return sprintf('/%s/%d', $entityTypeId, $id);
  }

  /**
   * Adds the derivative link relation type to the normalized link collection.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $object
   *   The resource object.
   * @param array $normalization
   *   The normalization.
   * @param array $localeInfo
   *   The locale info to add to the meta.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\CacheableNormalization
   *   The links normalization with meta.rel added.
   */
  protected static function addLocaleInfoMeta(ResourceObject $object, array $normalization, array $localeInfo) {
    $normalization['meta']['localeInfo'] = $localeInfo;

    return $normalization;

  }

}
