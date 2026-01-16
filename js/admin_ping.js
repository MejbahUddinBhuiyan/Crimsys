// Optional: ping server every 60 seconds to keep session warm.
// This does NOT block navigation or enforce any guard.
(function(){
  function ping(){
    fetch('/Crimsys/api/ping.php', {cache:'no-store'}).catch(()=>{});
  }
  ping();
  setInterval(ping, 60 * 1000);
})();
