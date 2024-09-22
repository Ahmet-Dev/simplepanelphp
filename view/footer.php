<?php
require_once 'view/head.php';
?>
<!-- Footer -->
<div class="container w-100 p-3">
    <footer class="d-flex flex-wrap justify-content-between align-items-center py-3 my-4 border-top">
        <div class="col-md-4 d-flex align-items-center">
            <a href="/" class="mb-3 me-2 mb-md-0 text-body-secondary text-decoration-none lh-1">
                <!-- Dinamik logo ekleme -->
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo !empty($page_data) ? htmlspecialchars($page_data['meta_author']) : (!empty($blog_data) ? htmlspecialchars($blog_data['meta_author']) : 'Varsayılan Yazar'); ?>" width="160" height="45">
            </a>
            <!-- Dinamik copyright metni -->
            <span class="mb-3 mb-md-0 text-body-secondary">
                &copy; <?php echo date('Y'); ?> <?php echo $cprt; ?>,
                <?php echo $company_name; ?>, 
                <?php echo $address; ?>
            </span>
        </div>

        <ul class="nav col-md-4 justify-content-end list-unstyled d-flex">
            <?php if (!empty($twitter_link) && $twitter_link !== '#'): ?>
                <li class="ms-3"><a class="text-body-secondary" href="<?php echo $twitter_link; ?>"><svg class="bi" width="24" height="24"><use xlink:href="#twitter"/></svg></a></li>
            <?php endif; ?>
            <?php if (!empty($instagram_link) && $instagram_link !== '#'): ?>
                <li class="ms-3"><a class="text-body-secondary" href="<?php echo $instagram_link; ?>"><svg class="bi" width="24" height="24"><use xlink:href="#instagram"/></svg></a></li>
            <?php endif; ?>
            <?php if (!empty($facebook_link) && $facebook_link !== '#'): ?>
                <li class="ms-3"><a class="text-body-secondary" href="<?php echo $facebook_link; ?>"><svg class="bi" width="24" height="24"><use xlink:href="#facebook"/></svg></a></li>
            <?php endif; ?>
        </ul>
    </footer>
</div>

<!-- Schema Markup (Yapılandırılmış Veri) -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "<?php echo htmlspecialchars($website_settings[0]['company_name']); ?>",
  "url": "<?php echo htmlspecialchars($website_settings[0]['website_url']); ?>",
  "logo": "<?php echo htmlspecialchars($website_settings[0]['logo']); ?>",
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "<?php echo htmlspecialchars($website_settings[0]['telephone']); ?>",
    "contactType": "Customer Service"
  },
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "<?php echo htmlspecialchars($website_settings[0]['street_address']); ?>",
    "addressLocality": "<?php echo htmlspecialchars($website_settings[0]['locality']); ?>",
    "postalCode": "<?php echo htmlspecialchars($website_settings[0]['postal_code']); ?>",
    "addressCountry": "<?php echo htmlspecialchars($website_settings[0]['country']); ?>"
  },
  "sameAs": [
    "<?php echo htmlspecialchars($social_media_links['facebook']); ?>",
    "<?php echo htmlspecialchars($social_media_links['twitter']); ?>",
    "<?php echo htmlspecialchars($social_media_links['instagram']); ?>"
  ]
}
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
$db = $dbo -> closeConnection();
?>
