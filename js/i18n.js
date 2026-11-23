(() => {
  const supportedLocales = ["sr-Latn", "sr-Cyrl", "en", "ru", "de"];
  const storageKey = document.documentElement.dataset.i18nStorageKey?.trim() || "smilecraft-locale";
  const runtimeUrl = document.currentScript?.src || window.location.href;
  const localeDirectory = new URL("../locales/", runtimeUrl);
  const flagDirectory = new URL("../images/", runtimeUrl);
  const flagFiles = {
    "sr-Latn": "rs.svg",
    "sr-Cyrl": "rs.svg",
    en: "us.svg",
    ru: "ru.svg",
    de: "de.svg",
  };
  const localeLabels = {
    "sr-Latn": "Srpski (latinica)",
    "sr-Cyrl": "Српски (ћирилица)",
    en: "English",
    ru: "Русский",
    de: "Deutsch",
  };
  const dictionaries = new Map();
  let locale = "sr-Latn";

  function normalizeLocale(value) {
    if (!value) return null;
    const normalized = value.toLowerCase();
    if (normalized.startsWith("sr")) {
      return normalized.includes("cyrl") ? "sr-Cyrl" : "sr-Latn";
    }
    return supportedLocales.find((item) => item.toLowerCase() === normalized)
      || supportedLocales.find((item) => item.toLowerCase() === normalized.split("-")[0])
      || null;
  }

  function detectLocale() {
    const queryLocale = normalizeLocale(new URLSearchParams(window.location.search).get("lang"));
    if (queryLocale) return queryLocale;
    try {
      const savedLocale = normalizeLocale(window.localStorage.getItem(storageKey));
      if (savedLocale) return savedLocale;
    } catch {
      // Storage may be unavailable in private or restricted browsing contexts.
    }
    const browserLocales = Array.isArray(navigator.languages) && navigator.languages.length
      ? navigator.languages
      : [navigator.language];
    return browserLocales.map(normalizeLocale).find(Boolean) || "sr-Latn";
  }

  async function loadDictionary(value) {
    if (!dictionaries.has(value)) {
      const response = await fetch(new URL(`${value}.json`, localeDirectory));
      if (!response.ok) throw new Error(`Unable to load locale ${value}`);
      dictionaries.set(value, await response.json());
    }
    return dictionaries.get(value);
  }

  function lookup(dictionary, key) {
    return key.split(".").reduce((value, part) => value?.[part], dictionary);
  }

  function t(key, params = {}) {
    const dictionary = dictionaries.get(locale) || {};
    const fallback = dictionaries.get("sr-Latn") || {};
    let value = lookup(dictionary, key) ?? lookup(fallback, key) ?? key;
    if (typeof value !== "string") return key;
    return value.replace(/\{(\w+)\}/g, (match, name) => (
      Object.prototype.hasOwnProperty.call(params, name) ? String(params[name]) : match
    ));
  }

  function localizedUrl(value, includeHash = false) {
    const url = new URL(window.location.href);
    url.searchParams.set("lang", value);
    if (!includeHash) url.hash = "";
    return url;
  }

  function updateLanguageLinks() {
    const canonical = document.querySelector('link[rel="canonical"]') || document.createElement("link");
    canonical.rel = "canonical";
    canonical.href = localizedUrl(locale).href;
    if (!canonical.isConnected) document.head.appendChild(canonical);

    document.querySelectorAll('link[rel="alternate"][hreflang]').forEach((link) => link.remove());
    supportedLocales.forEach((value) => {
      const alternate = document.createElement("link");
      alternate.rel = "alternate";
      alternate.hreflang = value;
      alternate.href = localizedUrl(value).href;
      document.head.appendChild(alternate);
    });
  }

  function createFlag(localeValue) {
    const image = document.createElement("img");
    image.className = "language-picker__flag";
    image.src = new URL(flagFiles[localeValue], flagDirectory).href;
    image.alt = "";
    image.setAttribute("aria-hidden", "true");
    return image;
  }

  function ensureLanguageSelector() {
    const header = document.querySelector(".header-inner");
    let selector = document.querySelector("[data-language-select]");
    if (!selector && !header) return;

    if (!selector) {
      let wrapper = document.querySelector(".site-header .language-switcher");
      if (!wrapper) {
        wrapper = document.createElement("div");
        wrapper.className = "language-switcher";
        header.appendChild(wrapper);
      }
      selector = document.createElement("select");
      selector.id = "site-language";
      supportedLocales.forEach((value) => {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = localeLabels[value];
        selector.appendChild(option);
      });
      selector.addEventListener("change", () => setLocale(selector.value));
      wrapper.appendChild(selector);
    }

    const wrapper = selector.parentElement;
    if (!wrapper) return;
    wrapper.classList.add("language-picker");
    selector.classList.add("language-picker__source");
    selector.setAttribute("aria-hidden", "true");
    selector.tabIndex = -1;
    selector.value = locale;
    selector.setAttribute("aria-label", t("language.select"));

    let trigger = wrapper.querySelector(".language-picker__trigger");
    let menu = wrapper.querySelector(".language-picker__menu");
    const menuId = `language-menu-${storageKey.replace(/[^a-zA-Z0-9_-]/g, "-")}`;

    if (!trigger) {
      trigger = document.createElement("button");
      trigger.className = "language-picker__trigger";
      trigger.type = "button";
      trigger.setAttribute("aria-haspopup", "menu");
      trigger.setAttribute("aria-expanded", "false");
      wrapper.appendChild(trigger);
    }

    if (!menu) {
      menu = document.createElement("div");
      menu.className = "language-picker__menu";
      menu.id = menuId;
      menu.setAttribute("role", "menu");
      menu.hidden = true;
      supportedLocales.forEach((value) => {
        const option = document.createElement("button");
        option.className = "language-picker__option";
        option.type = "button";
        option.dataset.locale = value;
        option.setAttribute("role", "menuitemradio");
        option.append(createFlag(value));
        const label = document.createElement("span");
        label.textContent = localeLabels[value];
        option.appendChild(label);
        option.addEventListener("click", () => {
          selector.value = value;
          selector.dispatchEvent(new Event("change", { bubbles: true }));
          menu.hidden = true;
          trigger.setAttribute("aria-expanded", "false");
          trigger.focus();
        });
        menu.appendChild(option);
      });
      wrapper.appendChild(menu);
    }

    trigger.setAttribute("aria-label", t("language.select"));
    trigger.setAttribute("aria-controls", menu.id);
    trigger.replaceChildren(createFlag(locale));
    const triggerLabel = document.createElement("span");
    triggerLabel.textContent = locale === "sr-Latn" ? "SR-Latn"
      : locale === "sr-Cyrl" ? "SR-Cyrl"
        : locale.toUpperCase();
    trigger.appendChild(triggerLabel);

    menu.querySelectorAll("[data-locale]").forEach((option) => {
      option.setAttribute("aria-checked", String(option.dataset.locale === locale));
    });

    if (trigger.dataset.languagePickerBound === "true") return;
    trigger.dataset.languagePickerBound = "true";
    trigger.addEventListener("click", () => {
      const open = trigger.getAttribute("aria-expanded") !== "true";
      trigger.setAttribute("aria-expanded", String(open));
      menu.hidden = !open;
      if (open) menu.querySelector("[data-locale]")?.focus();
    });
    trigger.addEventListener("keydown", (event) => {
      if (event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        trigger.setAttribute("aria-expanded", "true");
        menu.hidden = false;
        menu.querySelector("[data-locale]")?.focus();
      }
    });
    menu.addEventListener("keydown", (event) => {
      const options = [...menu.querySelectorAll("[data-locale]")];
      const currentIndex = options.indexOf(document.activeElement);
      let nextIndex = currentIndex;
      if (event.key === "ArrowDown") nextIndex = (currentIndex + 1) % options.length;
      else if (event.key === "ArrowUp") nextIndex = (currentIndex - 1 + options.length) % options.length;
      else if (event.key === "Home") nextIndex = 0;
      else if (event.key === "End") nextIndex = options.length - 1;
      else if (event.key === "Escape") {
        menu.hidden = true;
        trigger.setAttribute("aria-expanded", "false");
        trigger.focus();
        return;
      } else {
        return;
      }
      event.preventDefault();
      options[nextIndex]?.focus();
    });
    document.addEventListener("click", (event) => {
      if (!wrapper.contains(event.target)) {
        menu.hidden = true;
        trigger.setAttribute("aria-expanded", "false");
      }
    });
  }

  function translatePage() {
    document.documentElement.lang = locale;
    document.querySelectorAll("[data-i18n]").forEach((element) => {
      const value = t(element.dataset.i18n);
      if (value !== element.dataset.i18n) element.textContent = value;
    });
    document.querySelectorAll("[data-i18n-attr]").forEach((element) => {
      element.dataset.i18nAttr.split(";").forEach((entry) => {
        const [attribute, key] = entry.split(":").map((part) => part.trim());
        if (attribute && key) element.setAttribute(attribute, t(key));
      });
    });
    ensureLanguageSelector();
    updateLanguageLinks();
  }

  async function setLocale(value) {
    const nextLocale = normalizeLocale(value);
    if (!nextLocale) return false;
    await loadDictionary(nextLocale);
    locale = nextLocale;
    try {
      window.localStorage.setItem(storageKey, locale);
    } catch {
      // Continue with the selected locale even when persistence is unavailable.
    }
    const url = localizedUrl(locale, true);
    window.history.replaceState(window.history.state, "", `${url.pathname}${url.search}${url.hash}`);
    translatePage();
    window.dispatchEvent(new CustomEvent("smilecraft:localechange", { detail: { locale } }));
    return true;
  }

  const ready = (async () => {
    const savedOrDetectedLocale = detectLocale();
    try {
      await Promise.all([loadDictionary("sr-Latn"), loadDictionary(savedOrDetectedLocale)]);
      locale = savedOrDetectedLocale;
    } catch {
      locale = "sr-Latn";
      await loadDictionary("sr-Latn").catch(() => undefined);
    }
    translatePage();
    return locale;
  })();

  window.smilecraftI18n = {
    t,
    getLocale: () => locale,
    setLocale,
    ready,
    translatePage,
  };
})();
