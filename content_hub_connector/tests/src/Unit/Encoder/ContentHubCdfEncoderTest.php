<?php

/**
 * @file
 * Contains \Drupal\Tests\content_hub_connector\Unit\Encoder\ContentHubCdfEncoderTest.
 */

namespace Drupal\Tests\content_hub_connector\Unit\Encoder;

use Drupal\content_hub_connector\Encoder\ContentHubCdfEncoder;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\content_hub_connector\Encoder\ContentHubCdfEncoder
 * @group content_hub_connector
 */
class ContentHubCdfEncoderTest extends UnitTestCase {

  /**
   * Tests the supportsEncoding() method.
   */
  public function testSupportsEncoding() {
    $encoder = new ContentHubCdfEncoder();

    $this->assertTrue($encoder->supportsEncoding('content_hub_cdf'));
  }

}
