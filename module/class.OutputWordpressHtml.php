<?php

/**
 * posts.jsonファイルを元にテーマ配下のWPフォルダにHTMLを格納
 */
class OutputWordpressHtml
{
  /**
   * posts.jsonファイルを元にテーマ配下のWPフォルダにHTMLを格納
   */
  public static function execThis()
  {
    if (!file_exists(SYNC_DATA_POSTS_JSON_PATH)) return '';

    $json_data = mb_convert_encoding(file_get_contents(SYNC_DATA_POSTS_JSON_PATH), 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $posts_arr = json_decode($json_data, true);

    foreach ($posts_arr as $type => $posts) {
      if ($type === SYNC_DATA_PAGES_DIR_NAME) { // 固定ページ
        self::outputPagesFile($posts);
      } else if ($type === SYNC_DATA_POST_TYPE_DIR_NAME) { // カスタム投稿
        self::outputPostTypeFiles($posts);
      }
    }

    // ログの記録
    writeLogFunc(SYNC_DATA_POST_LOG_PREFIX, '[export] ファイル作成完了');
  }

  /**
   * 固定ページのディレクトリ&ファイル作成
   * @param array $posts 投稿情報
   */
  public static function outputPagesFile($posts)
  {
    // ~/WP/pagesのフォルダ作成
    createDirIfNotExists(SYNC_DATA_WP_PATH . '/' . SYNC_DATA_PAGES_DIR_NAME);

    // TODO:現状3階層までしか取得できないので、再帰処理に変更したい
    $parent_dir_slugs = [
      'third_level' => [],
      'secondary_level' => [],
    ];
    foreach ($posts as $post_slug => $post) {

      // 子ページがある場合、親ページのディレクトリ作成&ファイル更新
      if (array_key_exists('child_pages', $post)) {
        $parent_dir_path = SYNC_DATA_WP_PATH . '/' . SYNC_DATA_PAGES_DIR_NAME . '/' . $post_slug;
        array_push($parent_dir_slugs['secondary_level'], $parent_dir_path);
        createDirIfNotExists($parent_dir_path);

        $child_posts = $post['child_pages'];
        foreach ($child_posts as $child_slug => $child_post) {
          // 孫ページがある場合、子ページのディレクトリ作成&ファイル更新
          if (array_key_exists('child_pages', $child_post)) {
            $child_dir_path = SYNC_DATA_WP_PATH . '/' . SYNC_DATA_PAGES_DIR_NAME . '/' . $post_slug . '/' . $child_slug;
            array_push($parent_dir_slugs['third_level'], $child_dir_path);
            createDirIfNotExists($child_dir_path);

            $grandchild_posts = $child_post['child_pages'];
            foreach ($grandchild_posts as $grandchild_post) {
              self::updateTargetPost($grandchild_post, $grandchild_posts['post_content']);
            }
          }

          self::updateTargetPost($child_post, $child_post['post_content']);
        }
      }

      self::updateTargetPost($post, $post['post_content']);
    }

    // ファイルが存在しないディレクトリは削除
    foreach ($parent_dir_slugs as $level) {
      foreach ($level as $parent_dir_path) {
        self::removeDirNotExistFile($parent_dir_path);
      }
    }
  }

  /**
   * 特定の投稿にupdateFileContent関数を発火させる
   * @param array  $post 投稿情報
   * @param string $file_path ファイルのパス
   */
  public static function updateTargetPost($post, $file_path)
  {
    $post_id      = $post['ID'];
    $post_content = get_post($post_id)->post_content;
    $file_path    = array_key_exists('post_content', $post) ? SYNC_DATA_WP_PATH . '/' . $file_path : '';
    self::updateFileContent($post_content,  $file_path);
  }

  /**
   * カスタム投稿のディレクトリ&ファイル作成
   * @param array $posts 投稿情報
   */
  public static function outputPostTypeFiles($posts)
  {
    // ~/WP/post_typeのフォルダ作成
    createDirIfNotExists(SYNC_DATA_WP_PATH . '/' . SYNC_DATA_POST_TYPE_DIR_NAME);

    foreach ($posts as $post_type => $post_arr) {

      // ~/WP/post_type/カスタム投稿タイプのフォルダ作成
      $post_type_path = SYNC_DATA_WP_PATH . '/' . SYNC_DATA_POST_TYPE_DIR_NAME . '/' . $post_type;
      if (!empty($post_arr)) {
        createDirIfNotExists($post_type_path);
      }

      foreach ($post_arr as $post) {
        $post_id = $post['ID'];
        $post_content = get_post($post_id)->post_content;

        $file_path = array_key_exists('post_content', $post) ? SYNC_DATA_WP_PATH . '/' . $post['post_content'] : '';
        self::updateFileContent($post_content,  $file_path);
      }

      // ファイルが存在しないディレクトリは削除
      self::removeDirNotExistFile($post_type_path);
    }
  }

  /**
   * ファイルが存在しないディレクトリの削除
   * @param string $dir_path ディレクトリのパス
   */
  public static function removeDirNotExistFile($dir_path)
  {
    $dir_files = glob($dir_path . '/*.*');
    if (empty($dir_files)) {
      removeDir($dir_path);
    }
  }

  /**
   * ファイルの更新
   * @param string $post_content ファイルのコンテンツ
   * @param string $post_file_path ファイルまでのパス
   */
  public static function updateFileContent($post_content, $post_file_path)
  {
    if (!empty($post_content) && !empty($post_file_path)) {
      file_put_contents($post_file_path, $post_content);
    }
  }
}
