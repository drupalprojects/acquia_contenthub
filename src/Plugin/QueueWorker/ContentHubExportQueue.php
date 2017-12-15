<?php

namespace Drupal\acquia_contenthub\Plugin\QueueWorker;

/**
 * Export content to Content Hub service.
 *
 * @QueueWorker(
 *   id = "acquia_contenthub_export_queue",
 *   title = @Translation("Export Content to Acquia Content Hub"),
 *   cron = {"time" = 60}
 * )
 */
class ContentHubExportQueue extends ContentHubExportQueueBase {}
