<?php

/**
 * メディアライブラリの操作(追加、削除、更新)
 */

class ControlMediaLibrary
{
  /**
   * 参照元のディレクトリから出力先のディレクトリへメディアをアップロードする
   * @param string $reference_directory_path 参照元のディレクトリへのパス
   * @param string $output_directory_path    出力先のディレクトリへのパス
   */
  public static function uploadTargetDirectory($reference_directory_path, $output_directory_path)
  {
    if (self::copyFolder($reference_directory_path, $output_directory_path)) {
      writeLogFunc(SYNC_DATA_MEDIA_LOG_PREFIX, '[import/export] ' . $reference_directory_path . 'から' . $output_directory_path . 'にアップロード成功');
    } else {
      writeLogFunc(SYNC_DATA_MEDIA_LOG_PREFIX, '[import/export] ' . $reference_directory_path . 'から' . $output_directory_path . 'にアップロード失敗');
    }
  }

  /**
   * 指定したディレクトリから指定したディレクトリにディレクトリごとコピーする
   * @param string $source_folder      コピー元のディレクトリ
   * @param string $destination_folder コピー先のディレクトリ
   * @return bool
   */
  public static function copyFolder($source_folder, $destination_folder)
  {
    // ソースディレクトリが存在しない場合は何もしない
    if (!file_exists($source_folder)) return false;

    // デスティネーションディレクトリが存在しない場合は作成
    if (!file_exists($destination_folder)) mkdir($destination_folder, 0755, true);

    // ソースディレクトリ内のすべてのファイルとディレクトリを取得
    $dir_contents = glob($source_folder . '/*');

    foreach ($dir_contents as $dir_content) {
      if (is_dir($dir_content)) { // ディレクトリの場合、再帰的にコピー
        $subfolder = basename($dir_content);
        self::copyFolder($dir_content, $destination_folder . '/' . $subfolder);
      } else { // ファイルの場合、コピー
        $file_name = basename($dir_content);
        copy($dir_content, $destination_folder . '/' . $file_name);
      }
    }

    return true;
  }

  /**
   * 既存のメディアライブラリの削除
   */
  public static function deleteMediaLibrary()
  {
    removeDir(SYNC_DATA_UPLOAD);            // uploadsディレクトリ削除
    createDirIfNotExists(SYNC_DATA_UPLOAD); // uploadsディレクトリ作成

    $media_posts = get_posts([
      'post_type' => 'attachment',
      'post_mime_type' => 'image',
      'posts_per_page' => -1,
      'post_status' => 'inherit',
    ]);

    foreach ($media_posts as $media_post) {
      /** @see https://developer.wordpress.org/reference/functions/wp_delete_attachment/ */
      wp_delete_attachment($media_post->ID, true);
    }

    writeLogFunc(SYNC_DATA_MEDIA_LOG_PREFIX, '[import] メディア情報を全て削除');
  }

  /**
   * メディアライブラリの追加
   */
  public static function addMediaLibrary()
  {
    // ファイルが存在しない場合、処理抜け
    if (!file_exists(SYNC_DATA_MEDIA_JSON_PATH)) return '';

    // json ⇨ 連想配列に変換
    $json_data = mb_convert_encoding(file_get_contents(SYNC_DATA_MEDIA_JSON_PATH), 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $media_arr = json_decode($json_data, true);

    foreach ($media_arr as $media) {
      $media_arg = [
        'post_name'      => $media['post_name'],
        'post_title'     => $media['post_title'],
        'post_status'    => $media['post_status'],
        'post_content'   => $media['post_content'],
        'post_mime_type' => $media['post_mime_type'],
      ];
      $file_path = $media['file_path']; // ファイルのルートパス

      /** @see https://developer.wordpress.org/reference/functions/wp_insert_attachment/ */
      $attachment_id = wp_insert_attachment($media_arg, $file_path);

      // wp_generate_attachment_metadataの前で宣言する必要あり
      if (!function_exists('wp_crop_image')) {
        include(ABSPATH . 'wp-admin/includes/image.php');
      }

      /** 
       * メディア（添付ファイル）のメタデータを生成
       * @see https://developer.wordpress.org/reference/functions/wp_generate_attachment_metadata/ 
       * */
      $attach_data = wp_generate_attachment_metadata(
        $attachment_id,
        SYNC_DATA_UPLOAD . '/' . $file_path
      );

      /** 
       * メディア（添付ファイル）を与えられたメタ情報で更新して、ファイルを再作成
       * @see https://developer.wordpress.org/reference/functions/wp_update_attachment_metadata/
       * */
      wp_update_attachment_metadata($attachment_id, $attach_data);
    }

    writeLogFunc(SYNC_DATA_MEDIA_LOG_PREFIX, '[import] メディア情報を全て追加');
  }
}
