<?php

require_once(SYNC_DATA_PATH . 'module/class.GetPostData.php');

/**
 * JSONファイルの更新
 */

class UpdateJson
{
  /**
   * メディアJSONファイルの作成 / 更新
   */
  public static function updateMediaJson()
  {
    $media_posts = get_posts([
      'post_type'      => 'attachment',
      'post_mime_type' => 'image',
      'posts_per_page' => -1,
      'post_status'    => 'inherit',
    ]);

    $media_arr  = [];
    foreach ($media_posts as $media_post) {
      $file_root_path = !empty(wp_get_attachment_metadata($media_post->ID)) ? wp_get_attachment_metadata($media_post->ID)['file'] : '';
      $media_arr[] = [
        'post_name'      => $media_post->post_name,
        'post_title'     => $media_post->post_title,
        'post_status'    => $media_post->post_status,
        'post_content'   => $media_post->post_content,
        'post_mime_type' => $media_post->post_mime_type,
        'file_path'      => $file_root_path,
      ];
    }

    $media_json = json_encode($media_arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents(SYNC_DATA_MEDIA_JSON_PATH, $media_json);

    writeLogFunc(SYNC_DATA_MEDIA_LOG_PREFIX, '[export] メディアのJSONファイルの更新完了');
  }

  /**
   * タームJSONファイルの作成 / 更新
   */
  public static function updateTermJson()
  {
    $taxes_arr = [];
    $taxes     = get_taxonomies(['public' => true, '_builtin' => false]); // デフォルトのタグ以外のタクソノミー取得

    // タクソノミー分繰り返し処理
    foreach ($taxes as $tax) {
      $terms = get_terms($tax, ['hide_empty' => false]); // 投稿がないタクソノミーも取得

      if (!$terms) continue;

      if (!array_key_exists($tax, $taxes_arr))  $taxes_arr[$tax] = [];

      // ターム分繰り返し処理
      foreach ($terms as $term) {

        // insertに必要な情報をセット
        $taxes_arr[$tax][$term->slug] = [
          'name'        => $term->name,
          'slug'        => $term->slug,
          'description' => $term->description,
          'parent'      => $term->parent,
        ];

        // タームに紐づくCFを取得
        $acf_data = get_fields('term_' . $term->term_id);

        // ACFのフィールドタイプ別で連想配列の値を変える
        if ($acf_data) {
          $acf_arr  = [];
          foreach ($acf_data as $acf_key => $acf_val) {

            // フィールドタイプ別で処理を分岐
            $acf_field      = acf_get_field($acf_key);
            $acf_field_type = $acf_field['type'];
            switch ($acf_field_type) {
              case 'text':
                $acf_arr[$acf_key] = self::setAcfValue('text', $acf_val);
                break;
              case 'textarea':
                $acf_arr[$acf_key] = self::setAcfValue('textarea', $acf_val);
                break;
              case 'image':
                $acf_arr[$acf_key] = self::setAcfValue('image', $acf_val);
                break;
              case 'checkbox':
                $acf_arr[$acf_key] = self::setAcfValue('checkbox', $acf_val);
                break;
              case 'radio':
                $acf_arr[$acf_key] = self::setAcfValue('radio', $acf_val);
                break;
              case 'repeater':
                if (array_key_exists('sub_fields', $acf_field)) {
                  $acf_sub_field = $acf_field['sub_fields'][0]['type'];
                  $is_val_str    = ['text', 'textarea', 'radio'];
                  $acf_repeat    = [];
                  $acf_subtype   = '';
                  if (in_array($acf_sub_field, $is_val_str)) {
                    foreach ($acf_val as $acf_repeat_i => $acf_repeat_val) {
                      $acf_repeat[] = $acf_repeat_val;
                    }
                  } else if ($acf_sub_field === 'image') {
                    foreach ($acf_val as $acf_repeat_i => $acf_repeat_val) {
                      foreach ($acf_repeat_val as $acf_sub_key =>  $acf_sub_val) {
                        $acf_repeat_img = [];
                        $acf_repeat_img[$acf_sub_key] = $acf_sub_val['name'];
                        $acf_repeat[] = $acf_repeat_img;
                        $acf_subtype = 'image';
                      }
                    }
                  } else if ($acf_sub_field === 'checkbox') {
                    $acf_checkbox_arr = [];
                    foreach ($acf_val as $acf_repeat_i => $acf_repeat_val) {
                      $acf_repeat[] = $acf_repeat_val;
                    }
                  }
                  $acf_arr[$acf_key] = [
                    'acf_val' => $acf_repeat,
                    'type'    => 'repeater',
                    'subtype' => $acf_subtype,
                  ];
                }
                break;
              case 'group':
                if (array_key_exists('sub_fields', $acf_field)) {
                  $acf_group_values = [];
                  foreach ($acf_val as $acf_group_val) {
                    $acf_group_values[] = $acf_group_val;
                  }
                  $acf_group_values_i = 0;
                  foreach ($acf_field['sub_fields'] as $acf_sub_field) {
                    switch ($acf_sub_field['type']) {
                      case 'text':
                        $acf_arr[$acf_key]['acf_val'][] = $acf_group_values[$acf_group_values_i];
                        break;
                      case 'textarea':
                        $acf_arr[$acf_key]['acf_val'][] = $acf_group_values[$acf_group_values_i];
                        break;
                      case 'image':
                        $acf_arr[$acf_key]['acf_val'][] = $acf_group_values[$acf_group_values_i]['name'];
                        break;
                      case 'checkbox':
                        $acf_checkbox_arr = [];
                        foreach ($acf_group_values[$acf_group_values_i] as $acf_checkbox_i => $acf_checkbox_val) {
                          array_push($acf_checkbox_arr, $acf_checkbox_val);
                        }
                        $acf_arr[$acf_key]['acf_val'][] = $acf_checkbox_arr;
                        break;
                      case 'radio':
                        $acf_arr[$acf_key]['acf_val'][] = $acf_group_values[$acf_group_values_i];
                        break;
                      case 'repeater':
                        if (array_key_exists('sub_fields', $acf_sub_field)) {
                          $acf_bottom_field = $acf_sub_field['sub_fields'][0]['type'];
                          $is_val_str    = ['text', 'textarea', 'radio'];
                          $acf_repeat    = [];
                          $acf_subtype   = '';
                          if (in_array($acf_bottom_field, $is_val_str)) {
                            $acf_repeat[] = $acf_group_values[$acf_group_values_i];
                          } else if ($acf_bottom_field === 'image') {

                            foreach ($acf_group_values[$acf_group_values_i] as $acf_repeat_i => $acf_repeat_val) {
                              foreach ($acf_repeat_val as $acf_sub_key =>  $acf_sub_val) {
                                $acf_repeat_img = [];
                                $acf_repeat_img[$acf_sub_key] = $acf_sub_val['name'];
                                $acf_repeat[] = $acf_repeat_img;
                                $acf_subtype = 'image';
                              }
                            }
                          } else if ($acf_bottom_field === 'checkbox') {
                            $acf_checkbox_arr = [];
                            foreach ($acf_group_values[$acf_group_values_i] as $acf_repeat_i => $acf_repeat_val) {
                              $acf_repeat[] = $acf_repeat_val;
                            }
                          }
                          $acf_arr[$acf_key] = [
                            'acf_val' => $acf_repeat,
                            'subtype' => $acf_subtype,
                          ];
                        }
                        break;
                    }

                    $acf_group_values_i++;
                  }
                  $acf_arr[$acf_key]['type'] = 'group';
                }
                break;
            }
          }
          // ACFデータ追加
          if (!empty($acf_arr)) {
            $taxes_arr[$tax][$term->slug]['acf'] = $acf_arr;
          }
        }
      }
    }

    $taxes_json = json_encode($taxes_arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents(SYNC_DATA_TAX_JSON_PATH, $taxes_json);

    writeLogFunc(SYNC_DATA_TAX_LOG_PREFIX, '[export] ターム情報をJSONに書き出す');
  }

  /**
   * ACFの入力値を各項目ごとに処理を分岐させ、JSONにセットします。
   * @param string $field_type ACFの項目の種別を選択します。
   * @param array  $acf_val    ACFの値
   * @return array 各項目別の配列
   */
  public static function setAcfValue($field_type, $acf_val)
  {
    if ($field_type === 'text') {
      return [
        'acf_val' => $acf_val,
        'type'    => 'text',
      ];
    }

    if ($field_type === 'textarea') {
      return [
        'acf_val' => $acf_val,
        'type'    => 'textarea',
      ];
    }

    if ($field_type === 'image') {
      return [
        'acf_val' => $acf_val['name'],
        'type'    => 'image',
      ];
    }

    if ($field_type === 'checkbox') {
      $acf_checkbox_arr = [];
      foreach ($acf_val as $acf_checkbox_i => $acf_checkbox_val) {
        array_push($acf_checkbox_arr, $acf_checkbox_val);
      }
      return [
        'acf_val' => $acf_checkbox_arr,
        'type'    => 'checkbox',
      ];
    }

    if ($field_type === 'radio') {
      return [
        'acf_val' => $acf_val,
        'type'    => 'radio',
      ];
    }
  }

  /**
   * 投稿JSONファイルの作成 / 更新
   */
  public static function updatePostJson()
  {
    $posts_arr  = [
      'pages'     => GetPostData::getPagesData(),
      'post_type' => GetPostData::getPostTypesData(),
    ];
    $posts_json = json_encode($posts_arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents(SYNC_DATA_POSTS_JSON_PATH, $posts_json);

    writeLogFunc(SYNC_DATA_POST_LOG_PREFIX, '[export] 投稿情報をJSONに書き出す');
  }
}
