<?php
/**
 * @file
 * Contains \Drupal\acquia_contenthub\Cipher.
 */

namespace Drupal\acquia_contenthub;

/**
 * Cipher class that provides encryption.
 */
class Cipher implements CipherInterface {
  /**
   * The encryption algorithm.
   *
   * @var \Crypt_AES $cipher
   */
  private $cipher;
  /**
   * Whether it is plain text or not.
   *
   * @var bool $isPlainText
   */
  private $isPlainText;
  /**
   * Class Constructor.
   *
   * @param string $filepath
   *   The filepath to the selected file.
   */
  public function __construct($filepath) {
    if (!empty($filepath) && file_exists($filepath) && is_readable($filepath)) {
      $this->cipher = new \Crypt_AES(CRYPT_AES_MODE_ECB);
      $this->cipher->setKey(file_get_contents($filepath));
    }
    $this->isPlainText = !isset($this->cipher);
  }

  /**
   * Checks whether it is plain text.
   *
   * @return bool
   *   TRUE if plain text, FALSE otherwise.
   */
  public function isPlainText() {
    return $this->isPlainText;
  }

  /**
   * Obtains the Cipher.
   *
   * @return \Crypt_AES
   *   The Cipher object.
   */
  public function getCypher() {
    return $this->cipher;
  }

  /**
   * Encrypts the data.
   *
   * @param string $data
   *   The data contained in the file.
   *
   * @return string
   *   A encrypted string.
   */
  public function encrypt($data) {
    return $this->isPlainText ? $data : $this->cipher->encrypt($data);
  }

  /**
   * Decrypts the data.
   *
   * @param string $data
   *   The encrypted data.
   *
   * @return string|FALSE
   *   The decrypted string if it can be decrypted, FALSE otherwise.
   */
  public function decrypt($data) {
    return $this->isPlainText ? $data : $this->cipher->decrypt($data);
  }

}
