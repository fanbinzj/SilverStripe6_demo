<%-- Overrides the startup-theme footer: adds the footer note and disclaimer link from SiteConfig --%>
<footer class="footer">
    <div class="container container--footer">
        <nav aria-label="Footer">
            <ul class="footer-menu">
                <li class="footer-menu__item footer-menu__item--copyright">
                    &copy; $Now.Format("y") $SiteConfig.Title
                </li>
                <% loop $Menu(1) %>
                    <li class="footer-menu__item">
                        <a href="$Link" class="footer-menu__link">$MenuTitle</a>
                    </li>
                <% end_loop %>
                <% if $SiteConfig.DisclaimerPage.exists %>
                    <li class="footer-menu__item">
                        <a href="$SiteConfig.DisclaimerPage.Link" class="footer-menu__link">$SiteConfig.DisclaimerPage.MenuTitle</a>
                    </li>
                <% end_if %>
            </ul>
        </nav>
        <% if $SiteConfig.FooterNote %>
            <p class="footer__note">$SiteConfig.FooterNote</p>
        <% end_if %>
    </div>
</footer>
