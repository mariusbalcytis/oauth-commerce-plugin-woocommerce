<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php get_header('shop'); ?>
<?php do_action('woocommerce_before_main_content'); ?>

<p style="margin: 10px 0">
    Please provide login credentials to <a href="https://accounts.maba.lt" target="_blank">https://accounts.maba.lt</a>
    system.
</p>
<?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form action="" method="post">
    <div>
        <label for="username" style="width: 200px; display: inline-block">Your username:</label><input type="text" name="username" id="username"/>
    </div>
    <div>
        <label for="password" style="width: 200px; display: inline-block">Your password:</label><input type="password" name="password" id="password"/>
    </div>
    <div>
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>"/>
        <input type="submit" value="Confirm transaction"/>
    </div>
</form>

<?php do_action('woocommerce_after_main_content'); ?>
<?php do_action('woocommerce_sidebar'); ?>
<?php get_footer('shop'); ?>