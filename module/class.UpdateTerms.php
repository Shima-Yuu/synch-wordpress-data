<?php

/**
 * jsonからターム情報を更新する
 * 
 * @param string ターム情報のエクスポート先のjsonのパス
 */
class UpdateTerms
{
  /** @var string ターム情報のエクスポート先のjsonのパス */
  private $tax_json_path;

  /**
   * 初期実行
   * @param string $tax_json_path ターム情報のエクスポート先のjsonのパス
   * @return void updateTerms()関数
   */
  public function __construct($tax_json_path)
  {
    $this->tax_json_path = $tax_json_path;
    $this->updateTerms($this->tax_json_path);
  }

  /**
   * 指定したタクソノミーのターム情報をすべて削除
   * @param string $tax タクソノミー名
   */
  private function  deleteAllTermsInTax($tax)
  {
    // ターム情報を全て取得
    $terms = get_terms(array(
      'taxonomy' => $tax,
      'hide_empty' => false,
    ));

    if (!empty($terms) && !is_wp_error($terms)) {
      foreach ($terms as $term) {
        /** @see https://developer.wordpress.org/reference/functions/wp_delete_term/ */
        wp_delete_term($term->term_id, $tax);
      }
    }
  }

  /**
   * ターム情報を更新する
   */
  public function updateTerms()
  {
    // ファイルが存在しない場合、処理抜け
    if (!file_exists($this->tax_json_path)) return '';

    // json ⇨ 連想配列に変換
    $json_data = mb_convert_encoding(file_get_contents($this->tax_json_path), 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $tax_arr   = json_decode($json_data, true);

    // 空の場合、処理抜け
    if (empty($tax_arr)) return '';

    // ログのインスタンス作成
    $write_log = new WriteLog(SYNC_DATA_TAX_LOG_PREFIX);
    $file_path = SYNC_DATA_LOGS_PATH . '/' . $write_log->file_prefix;

    // タクソノミー毎に繰り返し処理
    foreach ($tax_arr as $tax_name => $terms) {

      $this->deleteAllTermsInTax($tax_name);

      foreach ($terms as $term) {
        // スラッグが重複しないか確認
        $existing_term = get_term_by('slug', $term['slug'], $tax_name);
        if (!$existing_term) {
          /** @see https://developer.wordpress.org/reference/functions/wp_insert_term/#return */
          $insert_term = wp_insert_term($term['name'], $tax_name, array(
            'slug'        => $term['slug'],
            'description' => $term['description'],
            'parent'      => $term['parent'],
          ));

          if (!is_wp_error($insert_term)) {

            // ACFデータ設定
            if (array_key_exists('acf', $term)) {
              $term_acfs = $term['acf'];
              foreach ($term_acfs as $term_acf_key => $term_acf_val) {
                if (
                  $term_acf_val['type'] === 'text' ||
                  $term_acf_val['type'] === 'textarea' ||
                  $term_acf_val['type'] === 'radio' ||
                  $term_acf_val['type'] === 'checkbox'
                ) {
                  update_field($term_acf_key, $term_acf_val['acf_val'], 'term_' . $insert_term['term_id']);
                } else if ($term_acf_val['type'] === 'image') {
                  global $wpdb;
                  $media_filename = $term_acf_val['acf_val'];
                  $query = $wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_name = %s",
                    $media_filename
                  );
                  $media_id = $wpdb->get_var($query);
                  if ($media_id) {
                    update_field($term_acf_key, $media_id, 'term_' . $insert_term['term_id']);
                  } else {
                    $write_log->lock();
                    $write_log->write($file_path, date('Y-m-d_H:i:s') . '	' . '[error] メディアのIDが正しく取得できませんでした。');
                    $write_log->unlock();
                  }
                } else if ($term_acf_val['type'] === 'repeater') {
                  if ($term_acf_val['subtype'] === 'image') {
                    $acf_repeat_images = [];
                    foreach ($term_acf_val['acf_val'] as $acf_images) {
                      foreach ($acf_images as $acf_image_key => $media_filename) {
                        global $wpdb;
                        $query = $wpdb->prepare(
                          "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_name = %s",
                          $media_filename
                        );
                        $media_id = $wpdb->get_var($query);
                        if ($media_id) {
                          $acf_repeat_images[][$acf_image_key] = $media_id;
                        }
                      }
                    }
                    if (!empty($acf_repeat_images)) {
                      update_field($term_acf_key, $acf_repeat_images, 'term_' . $insert_term['term_id']);
                    }
                  } else {
                    update_field($term_acf_key, $term_acf_val['acf_val'], 'term_' . $insert_term['term_id']);
                  }
                }
              }
            }
          } else {
            $write_log->lock();
            $write_log->write($file_path, date('Y-m-d_H:i:s') . '	' . '[error] ' . $insert_term->get_error_message());
            $write_log->unlock();
          }
        } else {
          $write_log->lock();
          $write_log->write($file_path, date('Y-m-d_H:i:s') . '	' . '[error] 「' . $term['slug'] . '」はすでに存在するようです。');
          $write_log->unlock();
        }
      }
    }
  }
}
