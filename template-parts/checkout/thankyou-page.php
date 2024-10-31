<div id="satimipay-succees">
    <?php if(PPCD_Satimipay::getInstance()->pdf_invoices_installed()) :

        $file_url = home_url('/wc-api/satimipay-download-pdf') . '?id=' . $order->get_id()."&key=". sanitize_text_field($_GET['key']);
        $send_file_url = home_url( '/wc-api/satimipay-send-order-pdf-to-email' ) . '?id=' . $order->get_id() . "&key=" . sanitize_text_field($_GET['key']);

        ?>
        <div class="satimpay-pdf">
            <h2>Votre facture</h2>
            <p>Nous avons établi une facture correspondante à votre commande et vous conseillons de garder une copie en votre possession.
            <br>
                <a download href="<?php echo esc_attr($file_url) ?>" id="satimipay-download-pdf">Télécharger</a>
                <a id="satimipay-succees-print-pdf" href="<?php echo esc_attr($file_url) ?>">Imprimer</a>
            </p>
            <p> Altérnativement, nous vous proposons d'envoyer la facture à l'email de votre choix.<p/>
            <p> <input type="email" id="satimipay-succees-send-pdf-email" value="<?php echo esc_html($order->get_billing_email()) ?>">
                <a id="satimipay-succees-send-pdf" href="<?php echo esc_attr($send_file_url) ?>"> Envoyer </a>
            </p>


        </div>

        <script>
            jQuery(function() {
              document.querySelector('#satimipay-succees-print-pdf').addEventListener('click', function (e) {
                e.preventDefault();

                var satim_frame = document.createElement('iframe');
                satim_frame.style.display = 'none';
                satim_frame.src  = this.href;

                satim_frame.onload = function() {
                  setTimeout(function () {
                    satim_frame.focus();
                    satim_frame.contentWindow.print();
                  }, 1);
                }

                document.body.appendChild(satim_frame);
              })

              document.querySelector('#satimipay-succees-send-pdf').addEventListener('click', function (e) {
                e.preventDefault();
                console.log(e);
                var email = document.querySelector('#satimipay-succees-send-pdf-email').value;

                jQuery
                    .get(this.href + '&email=' + encodeURIComponent(email))
                    .done(function (result) {
                      if(result.success) {
                        alert('La facture vous a été envoyée par email')
                      } else {
                        if(result.message) {
                          alert(result.message)
                        } else {
                          alert('Erreur d\'envoi. Veuillez réessayez.')
                        }
                      }
                    })
                    .fail(function () {
                      alert('Erreur d\'envoi. Veuillez réessayez.')
                    })
              })
            })
        </script>
    <?php endif; ?>

	<?php include 'thankyou-page-header.php' ?>

</div>
