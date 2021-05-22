THEME_PATH := user/themes/afj

# We use git-subrepo to handle sub-trees
# and avoid the usual git submodules hiccups

.PHONY: theme-pull theme-push
theme-pull:
	git subrepo pull $(THEME_PATH)

theme-push:
	git subrepo push $(THEME_PATH)
