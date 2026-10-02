// Checks SMTP login against the mail server, the same way contact.php does. Sends no mail.
// Usage: SMTP_PASS='...' node scripts/test-smtp.js [user] [host] [port]
const tls = require("tls");

const user = process.argv[2] || "lara@lp-consultora.com";
const host = process.argv[3] || "mail.lp-consultora.com";
const port = Number(process.argv[4] || 465);
const pass = process.env.SMTP_PASS;

if (!pass) {
  console.error("Falta la variable SMTP_PASS.");
  process.exit(2);
}

const b64 = (s) => Buffer.from(s, "utf8").toString("base64");
const steps = [
  () => "EHLO lp-consultora.com",
  () => "AUTH LOGIN",
  () => b64(user),
  () => b64(pass),
];
let step = 0;
let buf = "";

console.log(`Conectando a ${host}:${port} como ${user} (largo de contraseña: ${pass.length})`);
const sock = tls.connect({ host, port, servername: host }, () => console.log("TLS OK"));
sock.setEncoding("utf8");
sock.setTimeout(15000, () => {
  console.log("RESULTADO: timeout");
  sock.destroy();
  process.exit(1);
});
sock.on("error", (e) => {
  console.log("RESULTADO: error de conexión:", e.message);
  process.exit(1);
});

sock.on("data", (chunk) => {
  buf += chunk;
  // Wait for a final reply line ("NNN text", not "NNN-text")
  const lines = buf.split("\r\n");
  const last = lines.filter(Boolean).pop() || "";
  if (!/^\d{3} /.test(last)) return;
  const code = last.slice(0, 3);
  console.log("<-", last);
  buf = "";

  if (code === "235") {
    console.log("\nRESULTADO: LOGIN CORRECTO. Usuario y contraseña son válidos.");
    sock.write("QUIT\r\n");
    return;
  }
  if (code === "535") {
    console.log("\nRESULTADO: LOGIN RECHAZADO (535). Usuario o contraseña incorrectos.");
    sock.end("QUIT\r\n");
    return;
  }
  if (step < steps.length) {
    const cmd = steps[step++]();
    sock.write(cmd + "\r\n");
  } else {
    sock.end();
  }
});
sock.on("close", () => process.exit(0));
