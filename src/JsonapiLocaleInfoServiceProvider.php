<?php

declare(strict_types=1);

namespace Drupal\jsonapi_locale_info;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Custom service provider.
 *
 * Enables the "src-impostor-normalizers" directory to be within the
 * \Drupal\jsonapi\Normalizer namespace in order to circumvent the encapsulation
 * enforced by \Drupal\jsonapi\Serializer\Serializer::__construct().
 *
 * @see \Drupal\jsonapi_extras\JsonapiExtrasServiceProvider
 */
class JsonapiLocaleInfoServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Enable normalizers in the "src-impostor-normalizers" directory to be
    // within the \Drupal\jsonapi\Normalizer namespace in order to circumvent
    // the encapsulation enforced by
    // \Drupal\jsonapi\Serializer\Serializer::__construct().
    $container_namespaces = $container->getParameter('container.namespaces');
    $container_modules = $container->getParameter('container.modules');

    $jsonapi_impostor_path = dirname($container_modules['jsonapi_locale_info']['pathname']) . '/src-impostor-normalizers';
    $container_namespaces['Drupal\jsonapi\Normalizer\ImpostorFrom\jsonapi_locale_info'][] = $jsonapi_impostor_path;

    // Manually include the impostor definitions to avoid class not found error
    // during compilation, which gets triggered though cache-clear.
    $container->getDefinition('serializer.normalizer.resource_object.jsonapi_locale_info')
      ->setFile($jsonapi_impostor_path . '/ResourceObjectNormalizerImpostor.php');
  }

}
