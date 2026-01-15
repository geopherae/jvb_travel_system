<form action="../actions/delete_tour_package.php" method="POST">
  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
  <input type="hidden" name="package_id" value="25">
  <button type="submit">Test Archive</button>
</form>
