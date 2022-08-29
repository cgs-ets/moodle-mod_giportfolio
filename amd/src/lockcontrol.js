import Ajax from "core/ajax";

export const init = (hideall) => {

    const registerEventListeners = () => {
        // Lock the chapters
        document.querySelectorAll('#giportfolio-toc .fa-unlock').forEach(function (el) {
            el.addEventListener('click', lockEventHandler);
        });

        // Unlock the chapters
        document.querySelectorAll('#giportfolio-toc .fa-lock').forEach(function (el) {
            el.addEventListener('click', unlockEventHandler);
        });
    };

    const lockEventHandler = (e) => {
        const data = e.target.getAttribute("data-chapter");

        Ajax.call([{
            methodname: "mod_giportfolio_lock_chapter",
            args: {
                chapter: data
            },
            done: function (response) {
                const chapterids = JSON.parse(response.chapterid);

                chapterids.forEach(chid => {
                    const iEl = document.getElementById(`ch-lo-${chid.id}`);
                    iEl.classList.remove('fa-unlock');
                    iEl.classList.add('fa-lock');
                    // remove the listener 
                    iEl.removeEventListener("click", lockEventHandler);
                    iEl.addEventListener('click', unlockEventHandler);

                });
                // Hide add contribution button
                toggleAddContribution();
            },
            fail: function (reason) {
                console.error(reason);
            },
        }, ]);
    };

    const unlockEventHandler = (e) => {
        const data = e.target.getAttribute("data-chapter");

        Ajax.call([{
            methodname: "mod_giportfolio_unlock_chapter",
            args: {
                chapter: data
            },
            done: function (response) {
                const chapterids = JSON.parse(response.chapterid);

                chapterids.forEach(chid => {
                    const iEl = document.getElementById(`ch-lo-${chid.id}`);
                    iEl.classList.remove('fa-lock');
                    iEl.classList.add('fa-unlock');
                    iEl.addEventListener("click", lockEventHandler);
                    iEl.removeEventListener('click', unlockEventHandler);
                });
                /// Show add contribution button
                toggleAddContribution();
            },
            fail: function (reason) {
                console.error(reason);
            },
        }, ]);
    };

    // Show or hide contribution buttons, actions (edit, delete, hide, contribution) and add comment area
    const toggleAddContribution = function () {
        // Hide add contribution button
        const fEl = document.querySelector('form.add-contrib');

        if (fEl.classList.contains('add-contrib-lock')) {
            fEl.classList.remove('add-contrib-lock');
        } else {
            fEl.classList.add('add-contrib-lock');
        }
        console.log(fEl);
        // Hide contribution actions
        const actions = document.querySelectorAll('article.giportfolio-contribution > contribheader > a');
        actions.forEach(aEl => {
            if (aEl.classList.contains('add-contrib-lock')) {
                aEl.classList.remove('add-contrib-lock')
            } else {
                aEl.classList.add('add-contrib-lock')
            }

        });

        const commentarea = document.querySelectorAll('article.giportfolio-contribution > contribcomment');
        commentarea.forEach(area => {

            if (area.classList.contains('add-contrib-lock')) {
                area.classList.remove('add-contrib-lock');
            } else {
                area.classList.add('add-contrib-lock');
            }

        })


    }

    if (hideall) {
        toggleAddContribution();
    }

    registerEventListeners();
};