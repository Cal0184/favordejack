const searchInput = document.getElementById("searchInput");
const sections = [...document.querySelectorAll(".wiki-section")];
const navLinks = [...document.querySelectorAll("#wikiNav a")];
const toTop = document.getElementById("toTop");

searchInput.addEventListener("input", () => {
  const q = searchInput.value.trim().toLowerCase();
  sections.forEach(section => {
    const haystack = (section.innerText + " " + (section.dataset.search || "")).toLowerCase();
    section.classList.toggle("hidden-by-search", q && !haystack.includes(q));
  });
});

const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    navLinks.forEach(a => a.classList.remove("active"));
    const link = document.querySelector(`#wikiNav a[href="#${entry.target.id}"]`);
    if (link) link.classList.add("active");
  });
}, { rootMargin: "-25% 0px -65% 0px", threshold: 0 });

document.querySelectorAll("header[id], .wiki-section[id]").forEach(el => observer.observe(el));

window.addEventListener("scroll", () => {
  toTop.classList.toggle("show", window.scrollY > 600);
});
toTop.addEventListener("click", () => window.scrollTo({top:0, behavior:"smooth"}));
