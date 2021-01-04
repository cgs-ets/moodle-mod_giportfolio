M.mod_giportfolio_showMore = {
    init: function (Y, contributorid) {
        const anchor = document.getElementById(contributorid);
        anchor.addEventListener("click", this.display.bind(this, contributorid));

    },
    display: function (contributorid) {
        const icon = document.getElementById(contributorid);
        this.toggleIcon(true, contributorid);
        icon.title = 'Show Less';
        icon.innerHTML = '<i class = "fa">&#xf068;</i>';
        icon.addEventListener('click', this.hide.bind(this, contributorid));
    },
    hide: function (contributorid) {
        const icon = document.getElementById(contributorid);
        this.toggleIcon(false, contributorid);
        icon.title = 'Show More';
        icon.innerHTML = '<i class = "fa">&#xf067;</i>';
        icon.addEventListener('click', this.display.bind(this, contributorid));
    },

    toggleIcon: function (show, contributorid) {
        const links = Array.from(document.getElementsByClassName("contributor_" + contributorid));
        for (const link of links) {
            if (show) {
                link.classList.add('giportfolio-visible');
            } else {
                link.classList.remove('giportfolio-visible');
            }
        }
    }
}




