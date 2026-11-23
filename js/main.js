(() => {
  const config = window.clinicConfig;
  const menuButton = document.querySelector(".menu-toggle");
  const navigation = document.querySelector(".site-nav");
  const i18n = window.smilecraftI18n;
  const menuLabel = (open) => i18n
    ? i18n.t(open ? "site.accessibility.closeMenu" : "site.accessibility.openMenu")
    : open ? "Zatvori navigaciju" : "Otvori navigaciju";

  if (config) {
    document.querySelectorAll("[data-clinic-name]").forEach((element) => {
      element.textContent = config.name;
    });
    document.querySelectorAll("[data-clinic-phone]").forEach((element) => {
      element.textContent = config.phone;
      element.href = `tel:${config.phone.replace(/[^+\d]/g, "")}`;
    });
    document.querySelectorAll("[data-clinic-email]").forEach((element) => {
      element.textContent = config.email;
      element.href = `mailto:${config.email}`;
    });
    document.querySelectorAll("[data-clinic-address]").forEach((element) => {
      element.textContent = config.address;
    });

    document.documentElement.style.setProperty("--clinic-brand", config.primaryColor);
    document.documentElement.style.setProperty("--clinic-brand-soft", config.secondaryColor);
  }

  if (menuButton && navigation) {
    const closeMenu = (restoreFocus = false) => {
      const wasOpen = menuButton.getAttribute("aria-expanded") === "true";
      menuButton.setAttribute("aria-expanded", "false");
      menuButton.setAttribute("aria-label", menuLabel(false));
      navigation.classList.remove("is-open");
      document.body.classList.remove("menu-open");
      if (restoreFocus && wasOpen) menuButton.focus();
    };

    menuButton.addEventListener("click", () => {
      const isOpen = menuButton.getAttribute("aria-expanded") === "true";
      menuButton.setAttribute("aria-expanded", String(!isOpen));
      menuButton.setAttribute("aria-label", menuLabel(!isOpen));
      navigation.classList.toggle("is-open", !isOpen);
      document.body.classList.toggle("menu-open", !isOpen);
      if (!isOpen) navigation.querySelector("a")?.focus();
    });

    navigation.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", closeMenu);
    });

    document.addEventListener("click", (event) => {
      if (!navigation.contains(event.target) && !menuButton.contains(event.target)) {
        closeMenu();
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeMenu(true);
    });

    window.addEventListener("resize", () => {
      if (window.innerWidth > 860) closeMenu();
    });
  }

  window.addEventListener("smilecraft:localechange", () => {
    if (menuButton) {
      menuButton.setAttribute(
        "aria-label",
        menuLabel(menuButton.getAttribute("aria-expanded") === "true")
      );
    }
  });

  const year = document.querySelector("[data-current-year]");
  if (year) year.textContent = new Date().getFullYear();
})();
