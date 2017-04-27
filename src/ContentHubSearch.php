<?php

namespace Drupal\acquia_contenthub;

use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Drupal\Component\Utility\Tags;

/**
 * Perform queries to the Content Hub "_search" endpoint [Elasticsearch].
 */
class ContentHubSearch {

  /**
   * Content Hub Client Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientManager
   */
  protected $clientManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub.client_manager')
    );
  }

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager
   *   The client manager.
   */
  public function __construct(ClientManagerInterface $client_manager) {
    $this->clientManager = $client_manager;
  }

  /**
   * Executes an elasticsearch query.
   *
   * @param array $query
   *   Search query.
   *
   * @return mixed
   *   Returns elasticSearch query response hits.
   */
  public function executeSearchQuery(array $query) {
    if ($query_response = $this->clientManager->createRequest('searchEntity', [$query])) {
      return $query_response['hits'];
    }
    return FALSE;
  }

  /**
   * Helper function to build elasticsearch query for terms using AND operator.
   *
   * @param string $search_term
   *   Search term.
   *
   * @return mixed
   *   Returns query result.
   */
  public function getFilters($search_term) {
    if ($search_term) {
      $items = array_map('trim', explode(',', $search_term));
      $last_item = array_pop($items);

      $query['query'] = [
        'query_string' => [
          'query' => $last_item,
          'default_operator' => 'and',
        ],
      ];
      $query['_source'] = TRUE;
      $query['highlight'] = [
        'fields' => [
          '*' => new \stdClass(),
        ],
      ];
      $result = $this->executeSearchQuery($query);
      return $result ? $result['hits'] : FALSE;
    }
  }

  /**
   * Builds elasticsearch query to get filters name for auto suggestions.
   *
   * @param string $search_term
   *   Given search term.
   *
   * @return mixed
   *   Returns query result.
   */
  public function getReferenceFilters($search_term) {
    if ($search_term) {

      $match[] = ['match' => ['_all' => $search_term]];

      $query['query']['filtered']['query']['bool']['must'] = $match;
      $query['query']['filtered']['query']['bool']['must_not']['term']['data.type'] = 'taxonomy_term';
      $query['_source'] = TRUE;
      $query['highlight'] = [
        'fields' => [
          '*' => new \stdClass(),
        ],
      ];
      $result = $this->executeSearchQuery($query);

      return $result ? $result['hits'] : FALSE;
    }
  }

  /**
   * Builds Search query for given search terms.
   *
   * @param array $typed_terms
   *   Entered terms array.
   * @param string $webhook_uuid
   *   Webhook Uuid.
   * @param string $type
   *   Module Type to identify, which query needs to be executed.
   * @param array $options
   *   An associative array of options for this query, including:
   *   - count: number of items per page.
   *   - start: defines the offset to start from.
   *
   * @return int|mixed
   *   Returns query result.
   */
  public function getSearchResponse(array $typed_terms, $webhook_uuid = '', $type = '', array $options = []) {
    $origins = '';
    foreach ($typed_terms as $typed_term) {
      if ($typed_term['filter'] !== '_all') {
        if ($typed_term['filter'] == 'modified') {
          $dates = explode('to', $typed_term['value']);
          $from = isset($dates[0]) ? trim($dates[0]) : '';
          $to = isset($dates[1]) ? trim($dates[1]) : '';
          if (!empty($from)) {
            $query['filter']['range']['data.modified']['gte'] = $from;
          }
          if (!empty($to)) {
            $query['filter']['range']['data.modified']['lte'] = $to;
          }
          $query['filter']['range']['data.modified']['time_zone'] = '+1:00';
        }
        elseif ($typed_term['filter'] == 'origin') {
          $origins .= $typed_term['value'] . ',';
        }
        // Retrieve results for any language.
        else {
          $match[] = [
            'multi_match' => [
              'query' => $typed_term['value'],
              'fields' => ['data.attributes.' . $typed_term['filter'] . '.value.*'],
            ],
          ];
        }
      }
      else {
        $array_ref = $this->getReferenceDocs($typed_term['value']);
        if (is_array($array_ref)) {
          $tags = implode(', ', $array_ref);
        }
        if ($tags) {
          $match[] = ['match' => [$typed_term['filter'] => "*" . $typed_term['value'] . "*" . ',' . $tags]];
        }
        else {
          $match[] = [
            'match' => [
              $typed_term['filter'] => [
                "query" => "*" . $typed_term['value'] . "*" ,
                "operator" => "and",
              ],
            ],
          ];
        }
      }
    }

    if (isset($match)) {
      $query['query']['filtered']['query']['bool']['must'] = $match;
    }
    if (!empty($origins)) {
      $match[] = ['match' => ['data.origin' => $origins]];
      $query['query']['filtered']['query']['bool']['must'] = $match;
    }
    $query['query']['filtered']['filter']['term']['data.type'] = 'node';
    $query['size'] = !empty($options['count']) ? $options['count'] : 10;
    $query['from'] = !empty($options['start']) ? $options['start'] : 0;
    $query['highlight'] = [
      'fields' => [
        '*' => new \stdClass(),
      ],
    ];
    if (!empty($options['sort']) && strtolower($options['sort']) !== 'relevance') {
      $query['sort']['data.modified'] = strtolower($options['sort']);
    }
    switch ($type) {
      case 'content_hub':
        if (isset($webhook_uuid)) {
          $query['query']['filtered']['filter']['term']['_id'] = $webhook_uuid;
        }
    }
    return $this->executeSearchQuery($query);
  }

  /**
   * Helper function to get Uuids of referenced documents.
   *
   * @param string $str_val
   *   String value.
   *
   * @return array
   *   Reference terms Uuid array.
   */
  public function getReferenceDocs($str_val) {
    $ref_uuid = [];
    $ref_result = $this->getFilters($str_val);
    if ($ref_result) {
      foreach ($ref_result as $rows) {
        $ref_uuid[] = $rows['_id'];
      }
    }
    return $ref_uuid;
  }

  /**
   * Helper function to parse the given string with filters.
   *
   * @param string $str_val
   *   The string that needs to be parsed for querying elasticsearch.
   * @param string $webhook_uuid
   *   The Webhook Uuid.
   * @param string $type
   *   Module Type to identify, which query needs to be executed.
   * @param array $options
   *   An associative array of options for this query, including:
   *   - count: number of items per page.
   *   - start: defines the offset to start from.
   *
   * @return int|mixed
   *   Returns query response.
   */
  public function parseSearchString($str_val, $webhook_uuid = '', $type = '', array $options = []) {
    if ($str_val) {
      $search_terms = Tags::explode($str_val);
      foreach ($search_terms as $search_term) {
        $check_for_filter = preg_match('/[:]/', $search_term);
        if ($check_for_filter) {
          list($filter, $value) = explode(':', $search_term);
          $typed_terms[] = [
            'filter' => $filter,
            'value' => $value,
          ];
        }
        else {
          $typed_terms[] = [
            'filter' => '_all',
            'value' => $search_term,
          ];
        }
      }

      return $this->getSearchResponse($typed_terms, $webhook_uuid, $type, $options);
    }
  }

  /**
   * Builds tags list and executes query for a given webhook uuid.
   *
   * @param string $tags
   *   List of tags separated by comma.
   * @param string $webhook_uuid
   *   Webhook Uuid.
   * @param string $type
   *   Module Type to identify, which query needs to be executed.
   *
   * @return bool
   *   Returns query result.
   */
  public function buildTagsQuery($tags, $webhook_uuid, $type = '') {
    $result = $this->parseSearchString($tags, $webhook_uuid, $type);
    if ($result & !empty($result['total'])) {
      return $result['total'];
    }
    return 0;
  }

  /**
   * Builds elasticsearch query to retrieve data in reverse chronological order.
   *
   * @param array $options
   *   An associative array of options for this query, including:
   *   - count: number of items per page.
   *   - start: defines the offset to start from.
   *
   * @return mixed
   *   Returns query result.
   */
  public function buildChronologicalQuery(array $options = []) {

    $query['query']['match_all'] = new \stdClass();
    $query['sort']['data.modified'] = 'desc';
    if (!empty($options['sort']) && strtolower($options['sort']) !== 'relevance') {
      $query['sort']['data.modified'] = strtolower($options['sort']);
    }
    $query['filter']['term']['data.type'] = 'node';
    $query['size'] = !empty($options['count']) ? $options['count'] : 10;
    $query['from'] = !empty($options['start']) ? $options['start'] : 0;
    $result = $this->executeSearchQuery($query);

    return $result;
  }

}
