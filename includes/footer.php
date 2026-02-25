</div> </div> </div> <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function () {
        // সাইডবার হাইড/শো করার ফাংশন
        $('#sidebarCollapse').on('click', function () {
            $('#sidebar').toggleClass('active');
            // যদি ছোট স্ক্রিন হয়, তবে স্টাইল অ্যাডজাস্টমেন্ট
            if($('#sidebar').hasClass('active')) {
                $('#sidebar').css('margin-left', '-260px');
            } else {
                $('#sidebar').css('margin-left', '0');
            }
        });
    });
</script>

</body>
</html>