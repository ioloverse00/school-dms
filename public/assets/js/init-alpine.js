function data() {
  function getThemeFromLocalStorage() {
    // if user already changed the theme, use it
    if (window.localStorage.getItem('dark')) {
      return JSON.parse(window.localStorage.getItem('dark'))
    }

    // else return their preferences
    return (
      !!window.matchMedia &&
      window.matchMedia('(prefers-color-scheme: dark)').matches
    )
  }

  function setThemeToLocalStorage(value) {
    window.localStorage.setItem('dark', value)
  }

  return {
    dark: getThemeFromLocalStorage(),
    toggleTheme() {
      this.dark = !this.dark
      setThemeToLocalStorage(this.dark)
    },
    isSideMenuOpen: false,
    desktopViewport: window.innerWidth >= 768,
    isDesktopSidebarCollapsed: window.localStorage.getItem('portal-sidebar-collapsed') === 'true',
    toggleNavigation() {
      if (!this.desktopViewport) return this.toggleSideMenu()
      this.isDesktopSidebarCollapsed = !this.isDesktopSidebarCollapsed
      try { window.localStorage.setItem('portal-sidebar-collapsed', String(this.isDesktopSidebarCollapsed)) } catch (_) {}
      this.$nextTick(() => window.dispatchEvent(new Event('resize')))
    },
    toggleSideMenu() {
      if (this.isSideMenuOpen) return this.closeSideMenu()
      this.isSideMenuOpen = true
      this.$nextTick(() => {
        const focusClose = () => document.querySelector('#dashboard-sidebar button').focus()
        // Spark animates visibility/transform; focus after the open state reaches a frame.
        if (window.requestAnimationFrame) window.requestAnimationFrame(focusClose)
        else focusClose()
      })
    },
    closeSideMenu() {
      if (!this.isSideMenuOpen) return
      this.isSideMenuOpen = false
      this.$nextTick(() => {
        const toggle = document.getElementById('sidebar-toggle')
        if (toggle && toggle.offsetParent !== null) toggle.focus()
      })
    },
    trapSideMenuFocus(event) {
      if (!this.isSideMenuOpen) return
      const controls = Array.from(document.querySelectorAll(
        '#dashboard-sidebar a[href], #dashboard-sidebar button:not([disabled]), #dashboard-sidebar [tabindex="0"]'
      )).filter(element => element.offsetParent !== null)
      const first = controls[0]
      const last = controls[controls.length - 1]
      if (!first) return
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    },
    isNotificationsMenuOpen: ['all', 'unread'].includes(new URLSearchParams(window.location.search).get('notification_filter')),
    toggleNotificationsMenu() {
      this.isNotificationsMenuOpen = !this.isNotificationsMenuOpen
    },
    closeNotificationsMenu() {
      this.isNotificationsMenuOpen = false
    },
    isProfileMenuOpen: false,
    toggleProfileMenu() {
      this.isProfileMenuOpen = !this.isProfileMenuOpen
    },
    closeProfileMenu() {
      this.isProfileMenuOpen = false
    },
    isPagesMenuOpen: false,
    togglePagesMenu() {
      this.isPagesMenuOpen = !this.isPagesMenuOpen
    },
    // Modal
    isModalOpen: false,
    trapCleanup: null,
    openModal() {
      this.isModalOpen = true
      this.trapCleanup = focusTrap(document.querySelector('#modal'))
    },
    closeModal() {
      this.isModalOpen = false
      this.trapCleanup()
    },
  }
}
