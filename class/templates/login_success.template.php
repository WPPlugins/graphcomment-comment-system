<?php

?>

<!-- <script> var iframeObj = window.frameElement; iframeObj.classList.add('finished'); </script> -->
<script language="JavaScript" type="text/javascript">
  window.onload = function() {

    if (parent && typeof parent.oauthPopupClose === 'function') {
      parent.oauthPopupClose(true);
    }

    document.getElementById('oauth-reload-button').addEventListener('click', function(e) {
      e.preventDefault();
      parent.oauthPopupClose(false);
    });

    // close the window
    self.close();
  };
</script>

<!-- In case of the precedent strategy failure -->
<div id="connection_success_wrap">
  <div class="connection_success">
    <h3>Welcome home !</h3>
    <p>Congrats, you are now logged with your GraphComment account on your WordPress website.</p>
    <p>If you're not redirect, please click <a id="oauth-reload-button" href="#">here</a></p>
  </div>
</div>

<?php
