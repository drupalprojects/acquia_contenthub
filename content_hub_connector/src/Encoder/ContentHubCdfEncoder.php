<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Encoder\ContentHubCdfEncoder.
 */

namespace Drupal\content_hub_connector\Encoder;

use Symfony\Component\Serializer\Encoder\JsonEncoder as SymfonyJsonEncoder;

/**
 * Encodes Content Hub CDF data in JSON.
 *
 * Simply respond to content_hub_cdf format requests using the JSON encoder.
 */
class ContentHubCdfEncoder extends SymfonyJsonEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected $format = 'content_hub_cdf';

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {
    return $format == $this->format;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {
    return $format == $this->format;
  }

}
