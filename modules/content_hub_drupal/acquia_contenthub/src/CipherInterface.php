<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\CipherInterface.
 */

namespace Drupal\acquia_contenthub;

/**
 * Interface for CipherInterface.
 */
interface CipherInterface {

  /**
   * Checks whether the file is in plain text or not.
   *
   * @return bool
   *   TRUE if it is plain text, FALSE otherwise.
   */
  public function isPlainText();

  /**
   * Encrypts the data passed to this function.
   *
   * @param string $data
   *   Data to be encrypted.
   *
   * @return string
   *   The encrypted string.
   */
  public function encrypt($data);

  /**
   * Decrypts the data passed to this function.
   *
   * @param string $data
   *   Data to decrypt.
   *
   * @return string|FALSE
   *   Decrypted data, if it can be decrypted, FALSE otherwise.
   */
  public function decrypt($data);

}
