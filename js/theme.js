(() => {
  const root = document.documentElement;
  const storageKey = root.dataset.themeStorageKey || "smilecraft-theme";
  const systemPreference = window.matchMedia("(prefers-color-scheme: dark)");
  let hasSavedPreference = false;
  let theme = systemPreference.matches ? "dark" : "light";

  try {
    const savedTheme = window.localStorage.getItem(storageKey);
    if (savedTheme === "light" || savedTheme === "dark") {
      theme = savedTheme;
      hasSavedPreference = true;
    }
  } catch {
    // Theme selection still works for browsers that restrict local storage.
  }

  function updateThemeLabels() {
    const i18n = window.smilecraftI18n;
    const localized = (key, fallback) => {
      const value = i18n?.t(key);
      return value && value !== key ? value : fallback;
    };

    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      const isDark = theme === "dark";
      const nextLabel = isDark
        ? localized("site.theme.switchToLight", "Switch to light mode")
        : localized("site.theme.switchToDark", "Switch to dark mode");
      const modeLabel = isDark
        ? localized("site.theme.dark", "Dark mode")
        : localized("site.theme.light", "Light mode");
      const icon = isDark
        ? '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3.8"></circle><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path></svg>'
        : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 15.3A8.5 8.5 0 0 1 8.7 3.8 8.7 8.7 0 1 0 20.2 15.3Z"></path></svg>';

      button.setAttribute("aria-label", nextLabel);
      button.setAttribute("aria-pressed", String(isDark));
      button.title = nextLabel;
      button.innerHTML = `${icon}<span>${modeLabel}</span>`;
    });
  }

  function applyTheme(nextTheme, persist = false) {
    theme = nextTheme;
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
    const initialThemeColor = document.querySelector('meta[name="theme-color"]');
    if (initialThemeColor) {
      initialThemeColor.content = theme === "dark" ? "#101820" : "#FFFFFF";
    }

    const themeColor = document.querySelector('meta[name="theme-color"]');
    if (themeColor) {
      themeColor.content = theme === "dark" ? "#101820" : "#FFFFFF";
    }

    updateThemeLabels();

    if (persist) {
      hasSavedPreference = true;
      try {
        window.localStorage.setItem(storageKey, theme);
      } catch {
        // Keep the current selection for this page when storage is unavailable.
      }
    }
  }

  root.dataset.theme = theme;
  root.style.colorScheme = theme;

  function initializeControls() {
    const navigation = document.querySelector(".site-nav");
    if (navigation && !navigation.querySelector("[data-theme-toggle]")) {
      const button = document.createElement("button");
      button.type = "button";
      button.dataset.themeToggle = "";
      button.className = "theme-toggle";
      navigation.append(button);
    }

    applyTheme(theme);
    window.smilecraftI18n?.ready.then(updateThemeLabels);
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      button.addEventListener("click", () => {
        root.classList.add("theme-transition");
        applyTheme(theme === "dark" ? "light" : "dark", true);
        window.setTimeout(() => root.classList.remove("theme-transition"), 260);
      });
    });
  }

  window.addEventListener("smilecraft:localechange", updateThemeLabels);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeControls, { once: true });
  } else {
    initializeControls();
  }

  systemPreference.addEventListener?.("change", (event) => {
    if (!hasSavedPreference) applyTheme(event.matches ? "dark" : "light");
  });
})();
