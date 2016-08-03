<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Normalizer\NormalizerBase.
 */

namespace Drupal\acquia_contenthub\Normalizer;

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
