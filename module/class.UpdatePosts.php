<?php

/**
 * 投稿情報を更新する
 */
class UpdatePosts
{

  private $post_json_path;

  public function __construct($post_json_path)
  {
    $this->post_json_path = $post_json_path;
    $this->updatePosts();
  }

  /**
   * 投稿情報を更新する
   */
  private function updatePosts()
  {
    if (!file_exists($this->post_json_path)) return '';

    $json_data = mb_convert_encoding(file_get_contents($this->post_json_path), 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $posts_arr = json_decode($json_data, true);

    foreach ($posts_arr as $type => $posts) {

      if ($type === SYNC_DATA_POST_TYPE_DIR_NAME) {
        $this->deletePostTypeData();
        $this->updatePostTypeData($posts);

        writeLogFunc(SYNC_DATA_POST_LOG_PREFIX, '[import] カスタム投稿タイプのimport完了');
      } else if ($type === SYNC_DATA_PAGES_DIR_NAME) {
        $this->deletePagesPostData();
        $this->updatePagesData($posts);

        writeLogFunc(SYNC_DATA_POST_LOG_PREFIX, '[import] 固定ページのimport完了');
      }
    }
  }

  /**
   * カスタム投稿タイプの投稿を全て削除
   */
  private function deletePostTypeData()
  {
    $post_types = array_keys(get_post_types(['public'   => true, '_builtin' => false]));
    foreach ($post_types as $post_type) {
      $posts = get_posts([
        'post_status'    => 'any',
        'post_type'      => $post_type,
        'posts_per_page' => -1,
      ]);
      if (!empty($posts)) {
        foreach ($posts as $post) {
          wp_delete_post($post->ID, true);
        }
      }
    }
    writeLogFunc(SYNC_DATA_POST_LOG_PREFIX, '[delete] 全てのカスタム投稿タイプの投稿の削除完了');
  }

  /**
   * カスタム投稿タイプの投稿を全て削除
   */
  private function deletePagesPostData()
  {
    $pages_posts = get_posts([
      'post_status'    => 'any',
      'post_type'      => 'page',
      'posts_per_page' => -1,
    ]);
    foreach ($pages_posts as $pages_post) {
      if (!empty($pages_post)) {
        wp_delete_post($pages_post->ID, true);
      }
    }
    writeLogFunc(SYNC_DATA_POST_LOG_PREFIX, '[delete] 全ての固定ページの投稿の削除完了');
  }

  /**
   * カスタム投稿タイプの投稿情報を更新する
   * @param array $posts 投稿情報
   */
  private function updatePostTypeData($posts)
  {
    foreach ($posts as $post_type => $post_type_posts) {
      foreach ($post_type_posts as $post_slug => $post) {
        $post_value = [
          'import_id'   => $post['ID'],
          'post_title'  => $post['post_title'],
          'post_name'   => $post_slug,
          'post_type'   => $post_type,
          'post_status' => 'publish',
        ];
        $post_file_path = array_key_exists('post_content', $post) ? SYNC_DATA_WP_PATH . '/' . $post['post_content'] : '';
        if ($post_file_path && file_exists($post_file_path)) {
          $post_content = mb_convert_encoding(file_get_contents($post_file_path), 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
          $post_value['post_content'] = $post_content;
        }

        $insert_id = wp_insert_post($post_value);

        // ターム情報の登録
        if (!empty($post['terms']) && !empty($insert_id)) {
          foreach ($post['terms'] as $post_tax => $post_terms) {
            wp_set_object_terms($insert_id, $post_terms, $post_tax);
          }
        }

        // アイキャッチ画像登録
        $this->setThumbnail($post, $insert_id);
      }
    }
  }

  /**
   * 固定ページの投稿情報を更新する
   * @param array $posts 投稿情報
   */
  private function updatePagesData($posts)
  {
    // TODO:現状3階層までしか取得できないので、再帰処理に変更したい
    foreach ($posts as $post_slug => $post) {
      $this->insertPagePost($post, $post_slug);

      if (array_key_exists('child_pages', $post)) {
        $child_posts = $post['child_pages'];
        foreach ($child_posts as $child_post_slug => $child_post) {
          $this->insertPagePost($child_post, $child_post_slug);

          if (array_key_exists('child_pages', $child_post)) {
            $grandchild_posts = $child_post['child_pages'];
            foreach ($grandchild_posts as $grandchild_post_slug => $grandchild_post) {
              $this->insertPagePost($grandchild_post, $grandchild_post_slug);
            }
          }
        }
      }
    }
  }

  /**
   * 固定ページに投稿を登録する
   * @param array $post 投稿情報
   * @param array $post_slug 投稿のスラッグ
   */
  private function insertPagePost($post, $post_slug)
  {
    $post_parent = array_key_exists('post_parent', $post) ? $post['post_parent'] : 0;
    $post_value = [
      'import_id'   => $post['ID'],
      'post_title'  => $post['post_title'],
      'post_name'   => $post_slug,
      'post_status' => 'publish',
      'post_type'   => 'page',
      'post_parent' => $post_parent,
    ];

    $post_file_path = array_key_exists('post_content', $post) ? SYNC_DATA_WP_PATH . '/' . $post['post_content'] : '';
    if ($post_file_path && file_exists($post_file_path)) {
      $post_content = mb_convert_encoding(file_get_contents($post_file_path), 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
      $post_value['post_content'] = $post_content;
    }
    $insert_id = wp_insert_post($post_value);

    $this->setThumbnail($post, $insert_id);
  }

  /**
   * アイキャッチ画像を登録する
   * @param array $post jsonから読み込む1つのオブジェクト
   * @param string $insert_id 投稿ID
   */
  private function setThumbnail($post, $insert_id)
  {
    if (array_key_exists('thumbnail_file_name', $post)) {
      global $wpdb;
      $media_filename = $post['thumbnail_file_name'];

      // メディアの情報を取得するSQLクエリ
      $query = $wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_name = %s",
        $media_filename
      );

      $media_id = $wpdb->get_var($query);
      if ($media_id) set_post_thumbnail($insert_id, $media_id);
    }
  }
}
