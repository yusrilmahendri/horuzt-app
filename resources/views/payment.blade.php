<!DOCTYPE html>
<html>
<head>
    <title>Midtrans Payment Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <h2>Test Payment Midtrans</h2>
    <button id="pay-button">Bayar Sekarang</button>
<script type="text/javascript"
    src="https://app.sandbox.midtrans.com/snap/snap.js"
    data-client-key="{{ config('midtrans.client_key') }}"></script>


    <script type="text/javascript">
        document.getElementById('pay-button').onclick = function(){
            fetch('/payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    amount: 10000,
                    name: "Test User",
                    email: "user@example.com"
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.snap_token) {
                    window.snap.pay(data.snap_token, {
                        onSuccess: function(result){ console.log("Success", result); },
                        onPending: function(result){ console.log("Pending", result); },
                        onError: function(result){ console.log("Error", result); },
                        onClose: function(){ alert('Popup closed without finishing payment'); }
                    });
                } else {
                    alert("Error: " + data.error);
                }
            });
        };
    </script>
</body>
</html>
