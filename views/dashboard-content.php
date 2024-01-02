<div class="wrap synch_wordpress_data">
  <h2>Synch Wordpress Data</h2>
  <div class="postbox">
    <div class="inside">

      <div class="section">
        <h3>機能</h3>
        <dl>
          <dt>インポート</dt>
          <dd>
            ・テーマ配下のHTMLファイルから自動で投稿を更新<br>
            ・ターム情報の追加 / 更新<br>
            ・カスタムフィールドの追加 / 更新<br>
            ・メディアの追加 / 更新
          </dd>
        </dl>
        <dl>
          <dt>エクスポート</dt>
          <dd>
            ・テーマ配下に投稿のHTMLファイルを作成<br>
            ・jsonファイル(投稿情報、ターム情報、メディア情報)の作成 / 更新<br>
            ・メディアのディレクトリ / ファイル作成
          </dd>
        </dl>
      </div>

      <div class="section">
        <h3 class="u-mb0">インポート / エクスポート</h3>
        <form id="post_type_selection" class="js--form form" method="POST" enctype="multipart/form-data">
          <img src="<?= SYNC_DATA_URL ?>views/assets/images/loadingImg.gif" class="js--loadingImage loadingImage" width="32" height="32" alt="">
          <input type="submit" id="js--submit-import" value="インポートする" class="button u-mr30">
          <input type="submit" id="js--submit-export" value="エクスポートする" class="button">
          <p class="js--loadingText loadingText"></p>
        </form>
      </div>

      <div class="section">
        <h3>ルール</h3>
        <p class="u-mt0">・ACFのフィールドグループは、ACFプラグインからインポートする。</p>
        <p class="u-mt0">・上記のインポートをしてからSynch Wordpress Dataのインポートを実行する。</p>
        <p class="u-mt0">・トップページは必ずスラッグをtopと設定する</p>
      </div>
    </div>
  </div>
</div>

<script>
  const showLoadingAnimation = () => {
    $('.js--loadingImage').show();
    $('.js--form').toggleClass('is-loading');
  }
  const hideLoadingAnimation = () => {
    $('.js--loadingImage').hide();
    $('.js--form').toggleClass('is-loading');
  }
  const ajaxFunc = (this_el, action_name) => {
    this_el.preventDefault();

    const date = new Date();
    const log_time = `${date.getHours()}時${date.getMinutes()}分${date.getSeconds()}秒`

    $.ajax({
      type: "post",
      url: "<?= admin_url('admin-ajax.php'); ?>",
      data: {
        action: action_name
      },
      beforeSend: showLoadingAnimation(),
    }).done(function(data) {
      $('.js--loadingText').prepend(`<span class="loadingText--blue">通信成功：${log_time}</span><br>`)
      hideLoadingAnimation();
    }).fail(function(XMLHttpRequest, status, e) {
      $('.js--loadingText').prepend(`<span class="loadingText--red">通信失敗：${log_time}</span><br>`)
      hideLoadingAnimation();
    });
  };

  $('#js--submit-import').click(function(e) {
    ajaxFunc(e, "<?= ACTION_NAME_IMPORT; ?>");
  });
  $('#js--submit-export').click(function(e) {
    ajaxFunc(e, "<?= ACTION_NAME_EXPORT; ?>");
  });
</script>