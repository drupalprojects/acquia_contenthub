<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Normalizer\NormalizerBase.
 */

namespace Drupal\content_hub_connector\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase as SerializationNormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Base class for Normalizers.
 */
abstract class NormalizerBase extends SerializationNormalizerBase implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return parent::supportsDenormalization($data, $type, $format);
  }

}
