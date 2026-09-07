export function connectPageHtml(publicUrl: string): string {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>WPAgent — Link a WordPress site</title>
  <style>
    body { font-family: ui-sans-serif, system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; color: #1d2327; }
    label { display: block; margin: 0.75rem 0 0.25rem; font-weight: 600; }
    input { width: 100%; padding: 0.5rem; box-sizing: border-box; }
    button { margin-top: 1rem; padding: 0.6rem 1rem; }
    .note { color: #50575e; font-size: 0.95rem; }
    code { background: #f0f0f1; padding: 0.1rem 0.3rem; }
    .err { color: #b32d2e; }
    .ok { color: #008a20; }
  </style>
</head>
<body>
  <h1>Link a WordPress site</h1>
  <p class="note">Use a session token from <code>POST ${publicUrl}/auth/verify</code>, then paste the Application Password generated in wp-admin. The password is stored only for this session, encrypted at rest.</p>
  <form id="f">
    <label for="token">Session token</label>
    <input id="token" name="token" autocomplete="off" required />
    <label for="url">WordPress site URL</label>
    <input id="url" name="url" type="url" placeholder="https://example.com" required />
    <label for="username">WordPress username</label>
    <input id="username" name="username" required />
    <label for="password">Application Password</label>
    <input id="password" name="password" type="password" required />
    <button type="submit">Link site</button>
  </form>
  <p id="out"></p>
  <script>
    const out = document.getElementById("out");
    document.getElementById("f").addEventListener("submit", async (event) => {
      event.preventDefault();
      out.textContent = "Linking…";
      out.className = "";
      const body = {
        url: document.getElementById("url").value,
        username: document.getElementById("username").value,
        password: document.getElementById("password").value
      };
      const token = document.getElementById("token").value.trim();
      const res = await fetch("/auth/sites", {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: "Bearer " + token },
        body: JSON.stringify(body)
      });
      const data = await res.json();
      if (!res.ok) {
        out.className = "err";
        out.textContent = data.message || "Link failed";
        return;
      }
      out.className = "ok";
      out.textContent = "Linked. The AI client can now call tools with this session token.";
    });
  </script>
</body>
</html>`;
}
