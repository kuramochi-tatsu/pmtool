// assets/app.js
(() => {
  const KEY = "pmtool:lastTicketsURL";

  function isTicketsPage() {
    return location.pathname.endsWith("/tickets.php") || location.pathname.endsWith("tickets.php");
  }

  function normalizeTicketsLink(urlStr) {
    try {
      const u = new URL(urlStr, location.origin);
      if (u.searchParams.get("reset") === "1") return null;
      return u.toString();
    } catch {
      return null;
    }
  }

  function hasMeaningfulQuery(url) {
    for (const k of url.searchParams.keys()) {
      if (k !== "page" && k !== "reset") return true;
    }
    return false;
  }

  function setStored(urlStr) {
    const n = normalizeTicketsLink(urlStr);
    if (n) sessionStorage.setItem(KEY, n);
  }

  function getStored() {
    const s = sessionStorage.getItem(KEY);
    return s ? normalizeTicketsLink(s) : null;
  }

  function setupMobileMenu() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");
    const btn = document.getElementById("menuToggle");
    if (!sidebar || !overlay || !btn) return;

    const open = () => {
      sidebar.classList.add("is-open");
      overlay.classList.add("is-open");
    };
    const close = () => {
      sidebar.classList.remove("is-open");
      overlay.classList.remove("is-open");
    };

    btn.addEventListener("click", () => {
      const opened = sidebar.classList.contains("is-open");
      if (opened) close();
      else open();
    });

    overlay.addEventListener("click", close);

    // スマホ時：メニュー内リンクを押したら閉じる
    sidebar.addEventListener("click", (e) => {
      const a = e.target && e.target.closest ? e.target.closest("a") : null;
      if (!a) return;
      close();
    });

    // 画面が広くなったら強制クローズ
    window.addEventListener("resize", () => {
      if (window.matchMedia("(min-width: 921px)").matches) close();
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    setupMobileMenu();

    const currentUrl = new URL(location.href);
    const stored = getStored();

    // tickets.php で reset=1 が来たらキャッシュ削除（クリア用）
    if (isTicketsPage() && currentUrl.searchParams.get("reset") === "1") {
      sessionStorage.removeItem(KEY);

      currentUrl.searchParams.delete("reset");
      history.replaceState(null, "", currentUrl.pathname + (currentUrl.search || ""));
      return;
    }

    // tickets.php にクエリ無しで来た時、保存があれば自動復元
    if (isTicketsPage()) {
      if (!hasMeaningfulQuery(currentUrl) && stored && stored !== location.href) {
        location.replace(stored);
        return;
      }
      // tickets.php の現在URLを保存
      setStored(location.href);
    }

    // tickets.php へのリンクを保存済みURLへ差し替え
    // ※ reset=1 は除外 / ※ ソートリンク(.sortlink)は除外（重要）
    const latest = getStored();
    if (latest) {
      document
        .querySelectorAll('a[href="tickets.php"], a[href^="tickets.php?"]:not(.sortlink)')
        .forEach((a) => {
          const href = a.getAttribute("href") || "";
          try {
            const u = new URL(href, location.origin);
            if (u.searchParams.get("reset") === "1") return;
          } catch {}
          a.setAttribute("href", latest);
        });
    }

    // tickets.php では、リンククリック時も次のURLを保存（ソート/ページングも保存）
    document.addEventListener("click", (ev) => {
      const a = ev.target && ev.target.closest ? ev.target.closest("a") : null;
      if (!a) return;
      try {
        const u = new URL(a.href);
        if (u.origin !== location.origin) return;
        if (u.pathname.endsWith("tickets.php")) setStored(u.toString());
      } catch {}
    });

    // 検索フォーム送信時：送信内容をURLとして保存（確実に保持）
    const form = document.querySelector("form.filters");
    if (form && isTicketsPage()) {
      form.addEventListener("submit", () => {
        const fd = new FormData(form);
        const qs = new URLSearchParams();
        for (const [k, v] of fd.entries()) {
          const val = String(v ?? "").trim();
          if (val === "") continue;
          qs.append(k, val);
        }
        const target = "tickets.php" + (qs.toString() ? "?" + qs.toString() : "");
        setStored(new URL(target, location.origin).toString());
      });
    }
  });
})();
