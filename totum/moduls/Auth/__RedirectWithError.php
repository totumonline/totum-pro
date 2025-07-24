<script>

    let error = <?=json_encode($error, JSON_UNESCAPED_UNICODE);?>;

    sessionStorage.setItem('authError', error);
    window.location.href='/Auth/Login/'

</script>