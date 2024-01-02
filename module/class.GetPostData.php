<?php

/**
 * 投稿情報を取得する
 */
class GetPostData
{

  /** @var array jsonに記載するkeyを定義 */
  public static $post_json_keys = [
    'ID' => '',
    'post_content' => '',
    'post_title' => '',
  ];

  /**
   * カスタム投稿の投稿情報を取得
   * @return array カスタム投稿の投稿情報の配列
   */
  public static function getPostTypesData()
  {
    $post_type_data = [];
    $post_types = array_keys(get_post_types(['public'   => true, '_builtin' => false]));
    foreach ($post_types as $post_type) {
      $post_type_data[$post_type] = self::getTargetPostData($post_type);
    }
    return $post_type_data;
  }

  /**
   * 指定したカスタム投稿のページ情報を取得する
   * @param  string $post_type_slug カスタム投稿のスラッグ
   * @return array  getPageDataと同じフォーマットでデータを返却
   */
  public static function getTargetPostData($post_type_slug)
  {
    $post_archive_obj = get_post_type_object($post_type_slug);
    if (empty($post_archive_obj)) return '';

    $posts = get_posts([
      'post_status'    => 'publish',
      'post_type'      => $post_type_slug,
      'posts_per_page' => -1,
    ]);
    $post_arr = [];
    if ($posts) {
      foreach ($posts as $post) {
        // insertに必要な情報を取得
        $post_slug = $post->post_name;
        $post_arr[$post_slug]                 = self::$post_json_keys;
        $post_arr[$post_slug]['ID']           = $post->ID;
        $post_arr[$post_slug]['post_content'] = 'post_type/' . $post_type_slug . '/' . $post->post_name . '.html';
        $post_arr[$post_slug]['post_title']   = $post->post_title;

        // アイキャッチ画像の設定
        $post_thumbnail = get_post_thumbnail_id($post->ID);
        if (!empty($post_thumbnail)) {
          $post_thumbnail_name = get_post($post_thumbnail)->post_name;
          $post_arr[$post_slug]['thumbnail_file_name'] = $post_thumbnail_name;
        }

        // タームの設定
        $post_arr[$post_slug]['terms'] = [];
        $post_taxonomies = get_taxonomies([
          'public' => true,
          'object_type' => [$post_type_slug],
          '_builtin' => false,
        ]);
        foreach ($post_taxonomies as $post_taxonomy) {
          $post_terms = get_terms($post_taxonomy);
          if (!empty($post_terms)) {
            foreach ($post_terms as $post_term) {
              $post_arr[$post_slug]['terms'][$post_taxonomy][] = $post_term->slug;
            }
          }
        }
      }
    }
    return $post_arr;
  }

  /**
   * $arr配列を階層を持つ連想配列に変換。最後の連想配列に$valueの値を代入
   * @param  array  $arr 変換したい配列
   * @param  string $value 最後の連想配列に代入したい値
   * @return array  変換された連想配列
   */
  public static function createAssociativeArray($arr, $value)
  {
    $result = [];
    if (count($arr) > 1) {
      $key = array_shift($arr);
      $result[$key] = self::createAssociativeArray($arr, $value);
    } elseif (count($arr) === 1) {
      $result[$arr[0]] = $value;
    }
    return $result;
  }

  /**
   * 指定した固定ページの親階層のページを再帰的に取得する関数
   * @param  int   $page_id 対象の固定ページのID
   * @return array 親階層のページの情報を格納した配列
   */
  public static function getAncestorsRecursive($page_id)
  {
    $ancestors = array();

    // 指定した固定ページの親を取得
    $parent_id = wp_get_post_parent_id($page_id);

    // 親が存在する場合、再帰的に親ページを取得
    if ($parent_id) {
      $parent_page = get_post($parent_id);
      $parent_slug = $parent_page->post_name;

      // TODO:現状3階層までしか取得できないので、再帰処理に変更したい
      $parent_parent_id   = wp_get_post_parent_id($parent_id);
      $parent_dir_path = '';
      if ($parent_parent_id) {
        $parent_parent_page = get_post($parent_parent_id);
        $parent_dir_path = $parent_parent_page->post_name . '/' . $parent_slug;
      } else {
        $parent_dir_path = $parent_slug;
      };

      // 子ページの情報を作成
      $child_slug                 = get_post($page_id)->post_name;
      $child_data                 = self::$post_json_keys;
      $child_data['ID']           = $page_id;
      $child_data['post_parent']  = get_post($page_id)->post_parent;
      $child_data['post_content'] = 'pages/' . $parent_dir_path . '/' . $child_slug . '.html';
      $child_data['post_title']   = get_post_field('post_title', $page_id);
      $child_thumbnail            = get_post_thumbnail_id(get_post($page_id)->ID);
      if (!empty($child_thumbnail)) {
        $post_thumbnail_name = get_post($child_thumbnail)->post_name;
        $child_data['thumbnail_file_name'] = $post_thumbnail_name;
      }

      // 親の配列に子の情報を追加
      if (!isset($ancestors[$parent_slug])) {
        $ancestors[$parent_slug] = [];
      }
      $ancestors[$parent_slug][$child_slug] = $child_data;

      // 親の親を再帰的に取得
      $parent_ancestors = self::getAncestorsRecursive($parent_id);

      // 親の情報をマージ
      foreach ($parent_ancestors as $parent_slug => $childData) {
        if (!isset($ancestors[$parent_slug])) {
          $ancestors[$parent_slug] = [];
        }
        $ancestors[$parent_slug] = array_merge($ancestors[$parent_slug], $childData);
      }
    }

    // 階層を整える
    $ancestors_keys   = array_reverse(array_keys($ancestors));
    $this_post        = array_key_first($ancestors) ? $ancestors[array_key_first($ancestors)] : '';
    $adjust_ancestors = self::createAssociativeArray($ancestors_keys, $this_post);

    return $adjust_ancestors;
  }


  /**
   * 固定ページの情報を取得
   * @return array 固定ページ情報の配列
   */
  public static function getPagesData()
  {
    $pages_arr = [];
    $pages = get_pages();
    foreach ($pages as $page) {
      if ($page->post_parent) {
        // TODO:現状3階層までしか取得できないので、再帰処理に変更したい
        $page_parent_arr      = self::getAncestorsRecursive($page->ID);
        foreach ($page_parent_arr as $page_parent_arr_key => $page_parent_arr_val) {
          foreach ($page_parent_arr_val as $child_key => $child_val) {
            if (count($child_val) === 1) {
              foreach ($child_val as $child_val_key => $child_val_val) {
                $pages_arr[$page_parent_arr_key]['child_pages'][$child_key]['child_pages'][$child_val_key] = $child_val_val;
              }
            } else {
              $pages_arr[$page_parent_arr_key]['child_pages'][$child_key] = $child_val;
            }
          }
        }
      } else {
        $pages_arr[$page->post_name]                 = self::$post_json_keys;
        $pages_arr[$page->post_name]['ID']           = $page->ID;
        $pages_arr[$page->post_name]['post_content'] = 'pages/' . $page->post_name . '.html';
        $pages_arr[$page->post_name]['post_title']   = $page->post_title;

        $pages_thumbnail = get_post_thumbnail_id($page->ID);
        if (!empty($pages_thumbnail)) {
          $post_thumbnail_name = get_post($pages_thumbnail)->post_name;
          $pages_arr[$page->post_name]['thumbnail_file_name'] = $post_thumbnail_name;
        }
      }
    }
    return $pages_arr;
  }
}
